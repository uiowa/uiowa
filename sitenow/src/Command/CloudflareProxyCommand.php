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
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Process\Process;

/**
 * Routes a SiteNow site's traffic through the Cloudflare proxy.
 */
#[AsCommand(
  name: 'cloudflare:proxy',
  description: "Proxy a site's DNS record through Cloudflare, then check its certificate, DNS and launch status.",
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
   * Public resolvers used to see whether cached answers have caught up.
   */
  const PUBLIC_RESOLVERS = ['1.1.1.1', '8.8.8.8'];

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
      ->addArgument('host', InputArgument::REQUIRED, 'The site host (e.g. foo.sites.uiowa.edu).')
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

    $options = [
      'dry-run' => $input->getOption('dry-run'),
      'yes' => $input->getOption('yes'),
    ];

    $plan = $this->decide(strtolower($input->getArgument('host')), $options);
    return $this->executePlan($io, $plan, $options);
  }

  /**
   * Produce the plan: validation, and on pass the steps to run.
   *
   * @param string $host
   *   The site host.
   * @param array $options
   *   Command options.
   *
   * @return \SiteNow\Plan\Plan
   *   The plan.
   */
  private function decide(string $host, array $options): Plan {
    $title = "cloudflare:proxy {$host}";
    $input = ['host' => $host];
    $token = $this->credentials()->cloudflareToken();

    $app = NULL;
    foreach ($this->manifest() as $name => $sites) {
      if (in_array($host, $sites, TRUE)) {
        $app = $name;
        break;
      }
    }

    $validation = $this->runChecks([
      $this->checkHostShell(),
      new Check(self::CHECK_CLOUDFLARE_TOKEN, fn(): CheckResult => $token
        ? CheckResult::pass()
        : CheckResult::fail('Cloudflare token not found. Set cloudflare.token in ' . Credentials::defaultPath() . '.')),
      new Check(self::CHECK_IN_MANIFEST, fn(): CheckResult => $app
        ? CheckResult::pass()
        : CheckResult::fail("{$host} is not in the manifest. If its DNS still points at Acquia, it may be a domain to reclaim.")),
    ]);

    if ($validation['overall'] === CheckStatus::Fail) {
      return new Plan($title, $input, $validation);
    }

    $api = new CloudflareApi($token);
    $zone = $api->zoneFor($host);
    $records = $zone ? $this->addressRecords($api->records($zone['id'], $host)) : [];
    $record = $records[0] ?? NULL;

    $validation = $this->mergeValidation($validation, $this->runChecks([
      new Check(self::CHECK_ZONE_ACTIVE, function () use ($zone, $host): CheckResult {
        if (!$zone) {
          return CheckResult::fail("No Cloudflare zone the token can see contains {$host}.");
        }
        return $zone['status'] === 'active'
          ? CheckResult::pass()
          : CheckResult::fail("Zone {$zone['name']} is {$zone['status']}: Cloudflare is not yet serving its DNS.");
      }),
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
      $plan->addStep("Turn on proxying for {$host}", fn() => $api->proxy($zone['id'], $record['id']));
    }
    $plan->addStep('Wait for the edge certificate to become active', fn(SymfonyStyle $io) => $this->awaitCertificate($io, $api, $zone['id'], $host));
    // A TTL of 1 means automatic, which Cloudflare serves as 300 seconds.
    $ttl = $record['ttl'] === 1 ? 300 : $record['ttl'];
    $plan->addStep('Check DNS resolves to Cloudflare', fn(SymfonyStyle $io) => $this->checkDns($io, $api, $zone, $host, $ttl));
    $plan->addStep('Check whether the site is launched', fn(SymfonyStyle $io) => $this->checkLaunched($io, $zone, $host));

    return $plan;
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
    $deadline = time() + self::CERTIFICATE_TIMEOUT;
    // FALSE, not NULL: NULL is a real status (no pack yet) that must print.
    $last = FALSE;

    while (TRUE) {
      $status = $api->certificateStatus($zoneId, $host);
      if ($status !== $last) {
        $io->writeln('  Certificate: ' . ($status ?? 'none yet'));
        $last = $status;
      }
      if ($status === 'active') {
        return;
      }
      if (time() >= $deadline) {
        throw new \RuntimeException(sprintf('Certificate for %s not active after %d minutes. Re-run to keep checking.', $host, self::CERTIFICATE_TIMEOUT / 60));
      }
      sleep(self::POLL_INTERVAL);
    }
  }

  /**
   * Check that Cloudflare answers with its edge, and whether resolvers agree.
   *
   * @throws \RuntimeException
   *   If Cloudflare's nameservers do not answer with the edge.
   */
  private function checkDns(SymfonyStyle $io, CloudflareApi $api, array $zone, string $host, int $previousTtl): void {
    $ranges = $api->edgeRanges();
    $isEdge = fn(array $ips) => $ips && !array_filter($ips, fn($ip) => !IpUtils::checkIp($ip, $ranges));

    if (!$isEdge($this->resolve($host, $zone['name_servers'][0]))) {
      throw new \RuntimeException("Cloudflare's nameservers are not answering {$host} with Cloudflare IPs.");
    }
    $io->writeln("  Cloudflare's nameservers: Cloudflare IPs");

    // Public resolvers keep a cached answer until the record's previous TTL
    // runs out, which can be a day.
    foreach (self::PUBLIC_RESOLVERS as $resolver) {
      $io->writeln($isEdge($this->resolve($host, $resolver))
        ? "  {$resolver}: Cloudflare IPs"
        : sprintf('  %s: still cached, catches up within %s', $resolver, $this->duration($previousTtl)));
    }
  }

  /**
   * Request the site through Cloudflare's edge and report whether it is live.
   *
   * @throws \RuntimeException
   *   On a TLS failure or an unexpected status.
   */
  private function checkLaunched(SymfonyStyle $io, array $zone, string $host): void {
    $ip = $this->resolve($host, $zone['name_servers'][0])[0];
    $address = str_contains($ip, ':') ? "[{$ip}]" : $ip;

    try {
      $status = (new Client())->get("https://{$host}/", [
        'allow_redirects' => FALSE,
        'http_errors' => FALSE,
        'timeout' => 15,
        'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$address}"]],
      ])->getStatusCode();
    }
    catch (GuzzleException $e) {
      throw new \RuntimeException("Request to {$host} through Cloudflare failed: {$e->getMessage()}", 0, $e);
    }

    // Acquia answers 404 for a host no launched site claims.
    if ($status === 404) {
      $io->writeln('  Site: not launched (404)');
      return;
    }
    if ($status >= 400) {
      throw new \RuntimeException("{$host} returned {$status} through Cloudflare.");
    }
    $io->writeln("  Site: launched ({$status})");
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

  /**
   * A TTL as hours or minutes.
   */
  private function duration(int $seconds): string {
    return $seconds >= 3600 ? round($seconds / 3600) . 'h' : max(1, round($seconds / 60)) . 'm';
  }

}
