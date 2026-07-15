<?php

namespace SiteNow\Command;

use SiteNow\Config\Applications;
use SiteNow\Traits\ParsesListOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Runs post-deploy updates across an application's multisites.
 *
 * The fleet half of the post-deploy update, invoked by the Acquia cloud hooks
 * after code lands on an environment. Builds the site list for the current
 * application, orders run_first sites ahead of the rest, and fans the per-site
 * site:update out with GNU parallel (sequential fallback off Acquia).
 */
#[AsCommand(
  name: 'deploy:update',
  description: "Run post-deploy updates across an application's multisites.",
)]
class DeployUpdateCommand extends Command {

  use ParsesListOptions;

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates the manifest, run_first
   *   config, and the sn binary used for each site:update.
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
      ->addOption('sites', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated site list to update instead of the application default (testing / targeted recovery).', '')
      ->addOption('concurrency', 'j', InputOption::VALUE_REQUIRED, 'Number of sites to update in parallel.', '3')
      ->addOption('ref', NULL, InputOption::VALUE_REQUIRED, 'Deployed branch or tag, recorded in the run-log markers.', '');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    $app = getenv('AH_SITE_GROUP') ?: 'local';
    $env = getenv('AH_SITE_ENVIRONMENT') ?: 'local';
    $is_acquia = (bool) getenv('AH_SITE_ENVIRONMENT');

    $sites = $this->siteList($input, $app, $is_acquia);
    if (!$sites) {
      $io->warning("No sites to update for application '{$app}'.");
      return Command::SUCCESS;
    }
    $sites = $this->runFirstOrder($sites);

    $concurrency = (int) $input->getOption('concurrency') ?: 3;
    $log_dir = $is_acquia ? '/shared/logs' : sys_get_temp_dir();
    $joblog = "{$log_dir}/sn_deploy_update.joblog";
    // Retained, human-readable log of the full per-site output, accumulated
    // across deploys and bracketed with run markers. The joblog holds only
    // per-site metadata and is overwritten each run; this preserves the output
    // itself for after-the-fact debugging. Expected to be managed by logrotate
    // on the server.
    $runlog = "{$log_dir}/sn_deploy_update.log";
    $ref = trim($input->getOption('ref'));

    $io->writeln(sprintf('Updating %d site(s) on %s, %d at a time.', count($sites), $app, $concurrency));

    $summary = $this->hasParallel()
      ? $this->runParallel($sites, $concurrency, $joblog, $runlog, $ref)
      : $this->runSequential($sites, $runlog, $ref);

    $this->printSummary($io, $summary);
    $this->notifySlack($app, $env, $ref, $summary, $runlog);

    if ($summary['mismatch']) {
      $io->warning('Config does not match on: ' . implode(', ', $summary['mismatch']));
    }
    if ($summary['failed']) {
      $io->error('Update failed for: ' . implode(', ', $summary['failed']));
      return Command::FAILURE;
    }
    $io->success('Updates completed for all sites.');
    return Command::SUCCESS;
  }

  /**
   * Resolve the list of sites to update.
   *
   * On Acquia the list comes from blt/manifest.yml keyed by AH_SITE_GROUP;
   * locally it comes from blt/local.blt.yml. The --sites option overrides both.
   */
  private function siteList(InputInterface $input, string $app, bool $is_acquia): array {
    $override = $this->parseList($input->getOption('sites'));
    if ($override) {
      return $override;
    }

    if (!$is_acquia) {
      $local = "{$this->repoRoot}/blt/local.blt.yml";
      return is_file($local) ? (Yaml::parseFile($local)['multisites'] ?? []) : [];
    }

    $manifest = Yaml::parseFile("{$this->repoRoot}/blt/manifest.yml") ?: [];
    return $manifest[$app] ?? [];
  }

  /**
   * Move run_first sites to the front, preserving their configured order.
   */
  protected function runFirstOrder(array $sites): array {
    $registry = new Applications("{$this->repoRoot}/sitenow/applications.yml");
    $run_first = $registry->runFirst();

    foreach (array_reverse($run_first) as $site) {
      $key = array_search($site, $sites, TRUE);
      if ($key !== FALSE) {
        unset($sites[$key]);
        array_unshift($sites, $site);
      }
    }
    return array_values($sites);
  }

  /**
   * Whether GNU parallel is available.
   */
  private function hasParallel(): bool {
    $which = new Process(['which', 'parallel']);
    $which->run();
    return $which->isSuccessful() && trim($which->getOutput()) !== '';
  }

  /**
   * Fan the site updates out with GNU parallel and classify the outcome.
   *
   * @return array{updated: int, skipped: int, mismatch: string[], failed: string[]}
   *   The per-site outcome summary.
   */
  private function runParallel(array $sites, int $concurrency, string $joblog, string $runlog, string $ref): array {
    $cmd = [
      'parallel',
      '-j', (string) $concurrency,
      '--joblog', $joblog,
      // Prefix each output line with its site so the captured log is
      // attributable per site rather than an interleaved blob.
      '--tag',
      "{$this->repoRoot}/sn", 'site:update', '{}',
      ':::', ...$sites,
    ];

    $process = new Process($cmd, $this->repoRoot);
    $process->setTimeout(NULL);
    // Start from a clean joblog so a prior run's rows cannot inflate this run's
    // classification. GNU parallel overwrites by default, but do not depend on
    // the installed version's behavior.
    @unlink($joblog);
    $log = $this->openRunLog($runlog, count($sites), $ref);
    $process->run(function ($type, $buffer) use ($log) {
      print $buffer;
      if ($log) {
        fwrite($log, $buffer);
      }
    });
    $this->closeRunLog($log, $ref);

    // Skips exit non-zero (SKIPPED), so parallel's own exit cannot tell skip
    // from failure; classify each site from the joblog instead.
    return $this->classifyJoblog($joblog);
  }

  /**
   * Run updates one site at a time (off Acquia, where parallel may be absent).
   *
   * @return array{updated: int, skipped: int, mismatch: string[], failed: string[]}
   *   The per-site outcome summary.
   */
  private function runSequential(array $sites, string $runlog, string $ref): array {
    $summary = ['updated' => 0, 'skipped' => 0, 'mismatch' => [], 'failed' => []];
    $log = $this->openRunLog($runlog, count($sites), $ref);
    foreach ($sites as $site) {
      if ($log) {
        fwrite($log, "----- {$site} -----\n");
      }
      $process = new Process(["{$this->repoRoot}/sn", 'site:update', $site], $this->repoRoot);
      $process->setTimeout(NULL);
      $process->run(function ($type, $buffer) use ($log) {
        print $buffer;
        if ($log) {
          fwrite($log, $buffer);
        }
      });
      $exit = $process->getExitCode();
      if ($exit === 0) {
        $summary['updated']++;
      }
      elseif ($exit === SiteUpdateCommand::SKIPPED) {
        $summary['skipped']++;
      }
      elseif ($exit === SiteUpdateCommand::CONFIG_MISMATCH) {
        $summary['mismatch'][] = $site;
      }
      else {
        $summary['failed'][] = $site;
      }
    }
    $this->closeRunLog($log, $ref);

    return $summary;
  }

  /**
   * Open the retained per-site output log and write a run-start marker.
   *
   * @param string $runlog
   *   Path to the retained log (appended to).
   * @param int $count
   *   Number of sites in this run, recorded in the start marker.
   * @param string $ref
   *   Deployed branch or tag, recorded in the marker when set.
   *
   * @return resource|null
   *   The open file handle, or NULL if it could not be opened, in which case
   *   output still streams to stdout and only the retained copy is lost.
   */
  private function openRunLog(string $runlog, int $count, string $ref) {
    $log = @fopen($runlog, 'a');
    if ($log === FALSE) {
      return NULL;
    }
    $tag = $ref !== '' ? " {$ref}" : '';
    fwrite($log, sprintf("===== START %s%s: %d site(s) =====\n", date('Y-m-d H:i:s'), $tag, $count));
    return $log;
  }

  /**
   * Write the run-end marker and close the retained output log.
   *
   * @param resource|null $log
   *   The handle from openRunLog(), or NULL if it could not be opened.
   * @param string $ref
   *   Deployed branch or tag, recorded in the marker when set.
   */
  private function closeRunLog($log, string $ref): void {
    if ($log === NULL) {
      return;
    }
    $tag = $ref !== '' ? " {$ref}" : '';
    fwrite($log, sprintf("===== END %s%s =====\n\n", date('Y-m-d H:i:s'), $tag));
    fclose($log);
  }

  /**
   * Classify per-site outcomes from a parallel joblog by each site's exit code.
   *
   * @return array{updated: int, skipped: int, mismatch: string[], failed: string[]}
   *   Updated and skipped counts, and the names of config-mismatch and failed
   *   sites.
   */
  protected function classifyJoblog(string $joblog): array {
    $summary = ['updated' => 0, 'skipped' => 0, 'mismatch' => [], 'failed' => []];
    if (!is_file($joblog)) {
      return $summary;
    }
    foreach (file($joblog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
      // Skip the header row.
      if ($i === 0) {
        continue;
      }
      $cols = explode("\t", $line);
      // Columns 6 and 8 are Exitval and Command (the site is the last token).
      if (!isset($cols[6], $cols[8])) {
        continue;
      }
      $exit = (int) $cols[6];
      $parts = explode(' ', trim($cols[8]));
      $site = end($parts);
      if ($exit === 0) {
        $summary['updated']++;
      }
      elseif ($exit === SiteUpdateCommand::SKIPPED) {
        $summary['skipped']++;
      }
      elseif ($exit === SiteUpdateCommand::CONFIG_MISMATCH) {
        $summary['mismatch'][] = $site;
      }
      else {
        $summary['failed'][] = $site;
      }
    }
    return $summary;
  }

  /**
   * Print the run summary: updated / skipped / failed counts.
   */
  private function printSummary(SymfonyStyle $io, array $summary): void {
    $io->writeln(sprintf(
      'Summary: %d updated, %d skipped, %d with config not matching, %d failed.',
      $summary['updated'],
      $summary['skipped'],
      count($summary['mismatch']),
      count($summary['failed'])
    ));
  }

  /**
   * Post the deploy outcome to Slack when a webhook is configured.
   *
   * The webhook URL comes from the SLACK_WEBHOOK_URL environment variable.
   * A missing webhook or a failed POST is recorded in the run log and never
   * fails the deploy, so a silent notification is diagnosable after the fact.
   *
   * @param string $app
   *   The application (AH_SITE_GROUP).
   * @param string $env
   *   The environment (AH_SITE_ENVIRONMENT).
   * @param string $ref
   *   The deployed branch or tag, if known.
   * @param array $summary
   *   The updated / skipped / failed outcome summary.
   * @param string $runlog
   *   Path to the retained run log, where a skip or failure is recorded.
   */
  private function notifySlack(string $app, string $env, string $ref, array $summary, string $runlog): void {
    $webhook = getenv('SLACK_WEBHOOK_URL');
    if (!$webhook) {
      $this->noteToRunLog($runlog, 'Slack notification skipped: SLACK_WEBHOOK_URL not set.');
      return;
    }

    // Compose one message from the outcome: a baseline count plus a clause for
    // each problem tier that applies.
    $where = "*{$app} {$env}*" . ($ref !== '' ? " ({$ref})" : '');
    $parts = [sprintf('%d updated, %d skipped', $summary['updated'], $summary['skipped'])];
    if ($summary['mismatch']) {
      $parts[] = sprintf('config does not match on %d: %s', count($summary['mismatch']), implode(', ', $summary['mismatch']));
    }
    if ($summary['failed']) {
      $parts[] = sprintf('FAILED on %d: %s', count($summary['failed']), implode(', ', $summary['failed']));
    }
    $message = sprintf('Deploy to %s: %s.', $where, implode('; ', $parts));

    // Emoji reflects the worst tier present.
    $emoji = ':mostly_sunny:';
    if ($summary['mismatch']) {
      $emoji = ':warning:';
    }
    if ($summary['failed']) {
      $emoji = ':rain_cloud:';
    }

    $payload = json_encode([
      'username' => 'SiteNow Deploy',
      'text' => $message,
      'icon_emoji' => $emoji,
    ]);
    // Cap the time spent so a slow webhook cannot stall the hook.
    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // A notification failure must never fail the deploy; record it and move on.
    if ($response === FALSE || $status < 200 || $status >= 300) {
      $detail = $error !== '' ? $error : "HTTP {$status}";
      $this->noteToRunLog($runlog, "Slack notification failed: {$detail}.");
    }
  }

  /**
   * Append a diagnostic line to the retained run log.
   *
   * The run log is closed by the time notifications run, so this reopens it to
   * record why a notification was skipped or failed. A write failure is
   * ignored: diagnostics must never fail the deploy.
   */
  private function noteToRunLog(string $runlog, string $message): void {
    $log = @fopen($runlog, 'a');
    if ($log === FALSE) {
      return;
    }
    fwrite($log, $message . "\n");
    fclose($log);
  }

}
