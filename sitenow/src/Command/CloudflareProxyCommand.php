<?php

namespace SiteNow\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SiteNow\Cloudflare\CloudflareApi;
use SiteNow\Config\Credentials;
use SiteNow\Plan\Check;
use SiteNow\Plan\CheckResult;
use SiteNow\Plan\CheckStatus;
use SiteNow\Plan\CommonChecks;
use SiteNow\Plan\Plan;
use SiteNow\Plan\PlanTrait;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Routes a SiteNow site's traffic through the Cloudflare proxy.
 */
#[AsCommand(
  name: 'cloudflare:proxy',
  description: "Proxy a site's DNS record through Cloudflare, or every Acquia record in a zone, then check SSL certificates and DNS.",
)]
class CloudflareProxyCommand extends Command {

  use SiteNowCommandsTrait;
  use PlanTrait;
  use CommonChecks;

  // Machine names recorded in validation results.
  const CHECK_CLOUDFLARE_TOKEN = 'has_cloudflare_token';
  const CHECK_IN_MANIFEST = 'in_manifest';
  const CHECK_ZONE_ACTIVE = 'zone_active';
  const CHECK_SINGLE_RECORD = 'single_record';
  const CHECK_REACHES_ACQUIA = 'reaches_acquia';

  /**
   * Record contents that point at Acquia.
   */
  const ACQUIA_TARGETS = ['acquia-dlb.its.uiowa.edu', '54.163.225.50'];

  /**
   * Seconds to wait for the edge certificate to become active.
   */
  const CERTIFICATE_TIMEOUT = 900;

  /**
   * Seconds between certificate status polls.
   */
  const POLL_INTERVAL = 15;

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root.
   */
  public function __construct(
    private string $repoRoot = '',
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('host', InputArgument::OPTIONAL, 'The site host (e.g. foo.sites.uiowa.edu). Omit with --zone.')
      ->addOption('zone', NULL, InputOption::VALUE_REQUIRED, "Proxy every unproxied Acquia record in a zone, starting with www and the zone's own name.")
      ->addOption('limit', NULL, InputOption::VALUE_REQUIRED, 'With --zone, proxy at most this many hosts.')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Report what would happen and exit, changing nothing.')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Proceed without prompting.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    if (!$this->requireManifest($io)) {
      return Command::FAILURE;
    }

    $host = $input->getArgument('host');
    $zone = $input->getOption('zone');
    $limit = $input->getOption('limit');

    if (!$host === !$zone) {
      $io->error('Pass either a host or --zone.');
      return Command::FAILURE;
    }
    if ($limit !== NULL && (!$zone || !ctype_digit($limit) || (int) $limit < 1)) {
      $io->error('--limit takes a positive number and needs --zone.');
      return Command::FAILURE;
    }

    $options = [
      'dry-run' => $input->getOption('dry-run'),
      'yes' => $input->getOption('yes'),
    ];

    $plan = $host
      ? $this->decide(strtolower($host))
      : $this->decideZone(strtolower($zone), $limit === NULL ? NULL : (int) $limit);

    if (!$plan->failed() && !$plan->steps()) {
      $this->renderPlan($io, $plan->title, $plan->summary, $plan->validation);
      $io->writeln('Nothing to proxy.');
      return Command::SUCCESS;
    }

    return $this->executePlan($io, $plan, $options);
  }

  /**
   * Produce the plan: validation, and on pass the steps to run.
   *
   * @param string $host
   *   The site host.
   *
   * @return \SiteNow\Plan\Plan
   *   The plan.
   */
  private function decide(string $host): Plan {
    $title = "cloudflare:proxy {$host}";
    $input = ['host' => $host];
    $token = $this->credentials()->cloudflareToken();
    $app = $this->hostApps()[$host] ?? NULL;

    $validation = $this->runChecks([
      new Check(self::CHECK_IN_MANIFEST, fn(): CheckResult => $app
        ? CheckResult::pass()
        : CheckResult::fail("{$host} is not in the manifest. If its DNS still points at Acquia, it may be a domain to reclaim.")),
      ...$this->setupChecks($token),
    ]);

    if ($validation['overall'] === CheckStatus::Fail) {
      return new Plan($title, $input, $validation);
    }

    $api = new CloudflareApi($token);
    $zone = $api->zoneFor($host);
    $records = $zone ? $this->addressRecords($api->records($zone['id'], $host)) : [];
    $record = $records[0] ?? NULL;

    $validation = $this->mergeValidation($validation, $this->runChecks([
      $this->checkZoneActive($zone, $host),
      new Check(self::CHECK_SINGLE_RECORD, fn(): CheckResult => count($records) === 1
        ? CheckResult::pass()
        : CheckResult::fail(sprintf('Expected one A, AAAA or CNAME record for %s, found %d.', $host, count($records)))),
      new Check(self::CHECK_REACHES_ACQUIA, fn(): CheckResult => !$record || $this->reachesAcquia($api, $record['content'])
        ? CheckResult::pass()
        : CheckResult::fail("{$host} points at {$record['content']}, which does not lead to Acquia.")),
    ]));

    $summary = [
      ['label' => 'Application', 'value' => $app],
      ['label' => 'Zone', 'value' => $zone['name'] ?? '-'],
    ];
    if ($record) {
      $summary[] = ['label' => 'Record', 'value' => "{$record['type']} → {$record['content']}"];
      $summary[] = ['label' => 'Proxied', 'value' => $record['proxied'] ? 'yes' : 'no'];
    }

    $plan = new Plan($title, $input, $validation, $summary);
    if ($plan->failed()) {
      return $plan;
    }

    if (!$record['proxied']) {
      $plan->addStep("Turn on proxying for {$host}", function (SymfonyStyle $io) use ($api, $zone, $record, $host): void {
        $api->proxy($zone['id'], $record['id']);
        $io->writeln("Proxy enabled for {$host}");
      });
    }
    $plan->addStep('Wait for the SSL certificate to become active', function (SymfonyStyle $io) use ($api, $zone, $record, $host): void {
      if ($record['proxied']) {
        $io->writeln("Proxy already enabled for {$host}");
      }
      $this->awaitCertificate($io, $api, $zone['id'], $host);
    });
    $plan->addStep('Check DNS routes through Cloudflare', function (SymfonyStyle $io) use ($zone, $host): void {
      $this->checkCloudflareRouting($zone, $host);
      $io->writeln('DNS through Cloudflare: Active');
    });

    return $plan;
  }

  /**
   * Produce the plan for every unproxied Acquia record in a zone.
   *
   * @param string $name
   *   The zone name, e.g. "admissions.uiowa.edu".
   * @param int|null $limit
   *   The most hosts to proxy, or NULL for all of them.
   *
   * @return \SiteNow\Plan\Plan
   *   The plan.
   */
  private function decideZone(string $name, ?int $limit): Plan {
    $title = "cloudflare:proxy --zone={$name}";
    $input = ['zone' => $name, 'limit' => $limit];
    $token = $this->credentials()->cloudflareToken();

    $validation = $this->runChecks($this->setupChecks($token));
    if ($validation['overall'] === CheckStatus::Fail) {
      return new Plan($title, $input, $validation);
    }

    $api = new CloudflareApi($token);
    $zone = $api->zoneFor($name);
    if ($zone && $zone['name'] !== $name) {
      $zone = NULL;
    }

    $validation = $this->mergeValidation($validation, $this->runChecks([$this->checkZoneActive($zone, $name)]));
    if ($validation['overall'] === CheckStatus::Fail) {
      return new Plan($title, $input, $validation);
    }

    $byHost = [];
    foreach ($this->addressRecords($api->records($zone['id'])) as $record) {
      $byHost[$record['name']][] = $record;
    }

    $apps = $this->hostApps();
    $leadHosts = ["www.{$name}", $name];
    $leads = [];
    $todo = [];
    $proxied = 0;
    $reclaim = [];
    $skipped = [];
    foreach ($byHost as $host => $records) {
      if (in_array($host, self::ACQUIA_TARGETS, TRUE) || !array_filter($records, fn($r) => $this->reachesAcquia($api, $r['content']))) {
        continue;
      }
      if (count($records) > 1) {
        $skipped[] = sprintf('%s (%d records)', $host, count($records));
      }
      elseif ($records[0]['proxied']) {
        $proxied++;
      }
      elseif (!isset($apps[$host])) {
        $reclaim[] = $host;
      }
      else {
        $todo[$host] = $records[0];
      }
      if (count($records) === 1 && isset($apps[$host]) && in_array($host, $leadHosts, TRUE)) {
        $leads[$host] = $records[0];
      }
    }

    $rank = fn($host) => match ($host) {
      "www.{$name}" => 0,
      $name => 1,
      default => 2,
    };
    uksort($todo, fn($a, $b) => [$rank($a), $a] <=> [$rank($b), $b]);
    if ($limit) {
      $todo = array_slice($todo, 0, $limit, TRUE);
    }

    $summary = [
      ['label' => 'Zone', 'value' => $name],
      ['label' => 'Proxied', 'value' => (string) $proxied],
      ['label' => 'To proxy', 'value' => $todo ? implode(', ', array_keys($todo)) : 'none'],
    ];
    if ($reclaim) {
      $summary[] = ['label' => 'Reclaim', 'value' => implode(', ', $reclaim) . ' (not in the manifest)'];
    }
    if ($skipped) {
      $summary[] = ['label' => 'Skipped', 'value' => implode(', ', $skipped)];
    }

    $plan = new Plan($title, $input, $validation, $summary);

    if (!$todo) {
      return $plan;
    }

    // The www host and the zone's own name each get a step, so a failure stops
    // the run before the batch. One already proxied is checked without being
    // proxied again.
    $first = [];
    foreach ($leadHosts as $host) {
      if (isset($leads[$host]) && ($leads[$host]['proxied'] || isset($todo[$host]))) {
        $first[$host] = $leads[$host];
      }
    }
    foreach ($first as $host => $record) {
      $label = $record['proxied'] ? "Check {$host}" : "Proxy {$host} and check it";
      $plan->addStep($label, function (SymfonyStyle $io) use ($api, $zone, $record, $host): void {
        if (!$record['proxied']) {
          $api->proxy($zone['id'], $record['id']);
          $io->writeln("Proxy enabled for {$host}");
        }
        else {
          $io->writeln("Proxy already enabled for {$host}");
        }
        $this->awaitCertificate($io, $api, $zone['id'], $host);
        $this->checkCloudflareRouting($zone, $host);
        $io->writeln('DNS through Cloudflare: Active');
        $io->newLine();
      });
    }

    $rest = array_diff_key($todo, $first);
    if ($rest) {
      $plan->addStep(sprintf($first ? 'Proxy %d more hosts, then check them' : 'Proxy %d hosts, then check them', count($rest)), fn(SymfonyStyle $io) => $this->proxyBatch($io, $api, $zone, $rest));
    }

    return $plan;
  }

  /**
   * Proxy a batch of records, then check each host and report in a table.
   *
   * A host that fails does not stop the others.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param \SiteNow\Cloudflare\CloudflareApi $api
   *   The Cloudflare API.
   * @param array $zone
   *   The zone.
   * @param array $records
   *   The records to proxy, keyed by host.
   *
   * @throws \RuntimeException
   *   If any host fails a check.
   */
  private function proxyBatch(SymfonyStyle $io, CloudflareApi $api, array $zone, array $records): void {
    $rows = [];
    $proxied = [];
    foreach ($records as $host => $record) {
      try {
        $api->proxy($zone['id'], $record['id']);
        $io->writeln("Proxy enabled for {$host}");
        $rows[$host] = [$host, '-', '-'];
        $proxied[] = $host;
      }
      catch (\Exception $e) {
        $io->writeln("Proxy failed for {$host}: {$e->getMessage()}");
        $rows[$host] = [$host, 'Proxy failed', '-'];
      }
    }

    $statuses = $proxied ? $this->awaitCertificates($io, $api, $zone['id'], $proxied) : [];

    $dots = '';
    foreach ($statuses as $host => $status) {
      $dots .= '.';
      $io->write("\rDNS through Cloudflare: Checking {$dots}");
      $rows[$host][1] = $status === 'active' ? 'Active' : ($status ?? 'not issued');
      if ($status === 'active') {
        try {
          $this->checkCloudflareRouting($zone, $host);
          $rows[$host][2] = 'Active';
        }
        catch (\Exception $e) {
          $rows[$host][2] = $e->getMessage();
        }
      }
    }
    $io->write("\r\033[K");

    $io->table(['Host', 'SSL Certificate', 'DNS through Cloudflare'], array_values($rows));

    $failed = array_filter($rows, fn($row) => $row[1] !== 'Active' || $row[2] !== 'Active');
    if ($failed) {
      throw new \RuntimeException(sprintf('%d of %d hosts did not pass every check. Re-check one with a single-host run.', count($failed), count($rows)));
    }
  }

  /**
   * The checks every run needs before it can talk to Cloudflare.
   *
   * @param string|null $token
   *   The Cloudflare token.
   *
   * @return \SiteNow\Plan\Check[]
   *   The checks.
   */
  private function setupChecks(?string $token): array {
    return [
      $this->checkHostShell(),
      new Check(self::CHECK_CLOUDFLARE_TOKEN, fn(): CheckResult => $token
        ? CheckResult::pass()
        : CheckResult::fail('Cloudflare token not found. Set cloudflare.token in ' . Credentials::defaultPath() . '.')),
    ];
  }

  /**
   * Check that a zone exists and Cloudflare is serving its DNS.
   *
   * @param array|null $zone
   *   The zone, or NULL when none was found.
   * @param string $name
   *   The host or zone name that was looked up.
   *
   * @return \SiteNow\Plan\Check
   *   The check.
   */
  private function checkZoneActive(?array $zone, string $name): Check {
    return new Check(self::CHECK_ZONE_ACTIVE, function () use ($zone, $name): CheckResult {
      if (!$zone) {
        return CheckResult::fail("No Cloudflare zone the token can see matches {$name}.");
      }
      return $zone['status'] === 'active'
        ? CheckResult::pass()
        : CheckResult::fail("Zone {$zone['name']} is {$zone['status']}: Cloudflare is not yet serving its DNS.");
    });
  }

  /**
   * Application names from the manifest, keyed by host and by its www host.
   *
   * @return array
   *   Application names keyed by host.
   */
  private function hostApps(): array {
    $apps = [];
    foreach ($this->manifest() as $app => $hosts) {
      foreach ($hosts as $host) {
        $apps[$host] = $app;
        $apps["www.{$host}"] ??= $app;
      }
    }
    return $apps;
  }

  /**
   * Keep the records that can be proxied.
   *
   * @param array $records
   *   DNS records of any type.
   *
   * @return array
   *   The A, AAAA and CNAME records.
   */
  private function addressRecords(array $records): array {
    return array_values(array_filter($records, fn($r) => in_array($r['type'], ['A', 'AAAA', 'CNAME'], TRUE)));
  }

  /**
   * Whether a record's content leads to Acquia, directly or through records.
   *
   * @param \SiteNow\Cloudflare\CloudflareApi $api
   *   The Cloudflare API.
   * @param string $content
   *   A record's content: a hostname or an IP.
   * @param int $depth
   *   Records followed so far, to stop on a loop.
   *
   * @return bool
   *   TRUE when the chain ends at Acquia.
   */
  private function reachesAcquia(CloudflareApi $api, string $content, int $depth = 0): bool {
    $content = rtrim(strtolower($content), '.');
    if (in_array($content, self::ACQUIA_TARGETS, TRUE)) {
      return TRUE;
    }
    if ($depth > 5) {
      return FALSE;
    }

    $zone = $api->zoneFor($content);
    if (!$zone) {
      $answers = array_map(fn($line) => rtrim($line, '.'), $this->dig($content));
      return (bool) array_intersect($answers, self::ACQUIA_TARGETS);
    }

    foreach ($this->addressRecords($api->records($zone['id'], $content)) as $record) {
      if ($this->reachesAcquia($api, $record['content'], $depth + 1)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Poll until the edge certificate covering a host is active.
   *
   * @throws \RuntimeException
   *   If it is not active before the timeout.
   */
  private function awaitCertificate(SymfonyStyle $io, CloudflareApi $api, string $zoneId, string $host): void {
    if ($this->awaitCertificates($io, $api, $zoneId, [$host])[$host] !== 'active') {
      throw new \RuntimeException('Re-run to keep checking.');
    }
  }

  /**
   * Poll until the edge certificates covering several hosts are active.
   *
   * @return array
   *   Each host's last certificate status, keyed by host. Some may not be
   *   active when the timeout passes.
   */
  private function awaitCertificates(SymfonyStyle $io, CloudflareApi $api, string $zoneId, array $hosts): array {
    $deadline = time() + self::CERTIFICATE_TIMEOUT;
    $label = count($hosts) === 1 ? 'SSL Certificate' : 'SSL Certificates';
    $dots = '';

    while (TRUE) {
      $statuses = $api->certificateStatuses($zoneId, $hosts);
      $active = count(array_keys($statuses, 'active', TRUE));
      $dots .= '.';
      if ($active === count($hosts)) {
        $io->writeln("\r\033[K{$label}: Active");
        return $statuses;
      }
      if (time() >= $deadline) {
        $io->writeln(count($hosts) === 1
          ? sprintf("\r\033[K%s: Timed out: still %s after %d minutes", $label, reset($statuses) ?? 'not issued', self::CERTIFICATE_TIMEOUT / 60)
          : sprintf("\r\033[K%s: Timed out: %d of %d not active after %d minutes", $label, count($hosts) - $active, count($hosts), self::CERTIFICATE_TIMEOUT / 60));
        return $statuses;
      }
      $io->write(count($hosts) === 1
        ? "\r{$label}: Checking {$dots}"
        : "\r{$label}: Checking {$dots} ({$active} of " . count($hosts) . ' active)');
      sleep(self::POLL_INTERVAL);
    }
  }

  /**
   * Request the site through Cloudflare's edge, as a visitor off VPN would.
   *
   * @throws \RuntimeException
   *   On a TLS failure, a response Cloudflare did not serve, or a Cloudflare
   *   error reaching Acquia.
   */
  private function checkCloudflareRouting(array $zone, string $host): void {
    $ips = $this->resolve($host, $zone['name_servers'][0]);
    if (!$ips) {
      throw new \RuntimeException("Cloudflare's nameservers returned no address for {$host}.");
    }
    $address = str_contains($ips[0], ':') ? "[{$ips[0]}]" : $ips[0];

    try {
      $response = (new Client())->get("https://{$host}/", [
        'allow_redirects' => FALSE,
        'http_errors' => FALSE,
        'timeout' => 15,
        'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$address}"]],
      ]);
    }
    catch (GuzzleException $e) {
      throw new \RuntimeException("Request to {$host} through Cloudflare failed: {$e->getMessage()}", 0, $e);
    }

    if (!$response->hasHeader('cf-ray')) {
      throw new \RuntimeException("The response from {$host} did not come through Cloudflare.");
    }
    // 52x statuses are Cloudflare failing to reach or trust Acquia.
    if ($response->getStatusCode() >= 500) {
      throw new \RuntimeException("{$host} returned {$response->getStatusCode()} through Cloudflare.");
    }
  }

  /**
   * The IP addresses a nameserver gives for a host.
   *
   * @return string[]
   *   The addresses, without any CNAMEs in the chain.
   */
  private function resolve(string $host, string $server): array {
    return array_values(array_filter($this->dig($host, $server), fn($line) => filter_var($line, FILTER_VALIDATE_IP)));
  }

  /**
   * Run a short-form dig lookup.
   *
   * @param string $name
   *   The name to look up.
   * @param string|null $server
   *   The nameserver to ask, or NULL for the system resolver.
   *
   * @return string[]
   *   The answer lines.
   */
  private function dig(string $name, ?string $server = NULL): array {
    $args = ['dig', '+short', '+time=3', '+tries=1', $name];
    if ($server) {
      $args[] = "@{$server}";
    }
    $process = new Process($args);
    $process->run();
    return array_filter(explode("\n", trim($process->getOutput())));
  }

}
