<?php

namespace SiteNow\Command;

use SiteNow\Config\Applications;
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
 * after code lands on an environment (post-code-update on a push to the tracked
 * branch, post-code-deploy on a release switch). Builds the site list for the
 * current application, orders run_first sites ahead of the rest, and fans the
 * per-site site:update out with GNU parallel (sequential fallback off Acquia).
 *
 * The site list comes from blt/manifest.yml on Acquia (keyed by AH_SITE_GROUP)
 * and from blt/local.blt.yml locally.
 */
#[AsCommand(
  name: 'deploy:update',
  description: "Run post-deploy updates across an application's multisites.",
)]
class DeployUpdateCommand extends Command {

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
    $this->notifySlack($app, $env, $ref, $summary);

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
   */
  private function siteList(InputInterface $input, string $app, bool $is_acquia): array {
    $override = array_filter(array_map('trim', explode(',', $input->getOption('sites'))));
    if ($override) {
      return array_values($override);
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
  private function runFirstOrder(array $sites): array {
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
    $log = $this->openRunLog($runlog, count($sites), $ref);
    $process->run(function ($type, $buffer) use ($log) {
      print $buffer;
      if ($log) {
        fwrite($log, $buffer);
      }
    });
    $this->closeRunLog($log, $ref);

    // Skips exit non-zero (SKIPPED), so parallel's own exit no longer tells
    // skip from failure; classify each site from the joblog instead.
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
   * Classify per-site outcomes from a parallel joblog.
   *
   * A site exits 0 when updated, SKIPPED when skipped, and CONFIG_MISMATCH when
   * it updated but its config does not match; any other non-zero code is a
   * failure.
   *
   * @return array{updated: int, skipped: int, mismatch: string[], failed: string[]}
   *   Updated and skipped counts, and the names of config-mismatch and failed
   *   sites.
   */
  private function classifyJoblog(string $joblog): array {
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
   * Ports BLT's post-code-update Slack notification. The webhook URL comes from
   * the SLACK_WEBHOOK_URL environment variable; without it this is a no-op, so
   * local runs stay silent.
   *
   * @param string $app
   *   The application (AH_SITE_GROUP).
   * @param string $env
   *   The environment (AH_SITE_ENVIRONMENT).
   * @param string $ref
   *   The deployed branch or tag, if known.
   * @param array $summary
   *   The updated / skipped / failed outcome summary.
   */
  private function notifySlack(string $app, string $env, string $ref, array $summary): void {
    $webhook = getenv('SLACK_WEBHOOK_URL');
    if (!$webhook) {
      return;
    }

    $where = "*{$app} {$env}*" . ($ref !== '' ? " ({$ref})" : '');
    $failed = $summary['failed'];
    $mismatch = $summary['mismatch'];
    if ($failed) {
      $emoji = ':rain_cloud:';
      $message = sprintf(
        'Deploy to %s FAILED: %d site(s) failed: %s.',
        $where, count($failed), implode(', ', $failed)
      );
    }
    elseif ($mismatch) {
      $emoji = ':warning:';
      $message = sprintf(
        'Deploy to %s completed, but config does not match on %d site(s): %s.',
        $where, count($mismatch), implode(', ', $mismatch)
      );
    }
    else {
      $emoji = ':mostly_sunny:';
      $message = sprintf(
        'Deploy to %s completed: %d updated, %d skipped.',
        $where, $summary['updated'], $summary['skipped']
      );
    }
    // When a run both failed and left config not matching, note the latter too.
    if ($failed && $mismatch) {
      $message .= sprintf(
        ' Config also does not match on %d site(s): %s.',
        count($mismatch), implode(', ', $mismatch)
      );
    }

    $payload = json_encode([
      'username' => 'SiteNow Deploy',
      'text' => $message,
      'icon_emoji' => $emoji,
    ]);
    // A notification failure must never fail the deploy; ignore the result and
    // cap the time spent so a slow webhook cannot stall the hook.
    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'payload=' . $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
  }

}
