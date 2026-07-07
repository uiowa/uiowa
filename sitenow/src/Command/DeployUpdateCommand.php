<?php

namespace SiteNow\Command;

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
      ->addOption('concurrency', 'j', InputOption::VALUE_REQUIRED, 'Number of sites to update in parallel.', '3');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    $app = getenv('AH_SITE_GROUP') ?: 'local';
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

    $io->writeln(sprintf('Updating %d site(s) on %s, %d at a time.', count($sites), $app, $concurrency));

    if ($this->hasParallel()) {
      return $this->runParallel($io, $sites, $concurrency, $joblog, $runlog);
    }
    return $this->runSequential($io, $sites, $runlog);
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
    $blt = "{$this->repoRoot}/blt/blt.yml";
    $run_first = is_file($blt) ? (Yaml::parseFile($blt)['uiowa']['run_first'] ?? []) : [];

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
   * Fan the site updates out with GNU parallel; aggregate from the exit code.
   */
  private function runParallel(SymfonyStyle $io, array $sites, int $concurrency, string $joblog, string $runlog): int {
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
    $log = $this->openRunLog($runlog, count($sites));
    $process->run(function ($type, $buffer) use ($log) {
      print $buffer;
      if ($log) {
        fwrite($log, $buffer);
      }
    });
    $this->closeRunLog($log);

    // Skips exit non-zero (SKIPPED), so parallel's own exit no longer tells
    // skip from failure; classify each site from the joblog instead.
    $summary = $this->classifyJoblog($joblog);
    $this->printSummary($io, $summary);
    if ($summary['failed']) {
      $io->error('Update failed for: ' . implode(', ', $summary['failed']));
      return Command::FAILURE;
    }

    $io->success('Updates completed for all sites.');
    return Command::SUCCESS;
  }

  /**
   * Run updates one site at a time (off Acquia, where parallel may be absent).
   */
  private function runSequential(SymfonyStyle $io, array $sites, string $runlog): int {
    $summary = ['updated' => 0, 'skipped' => 0, 'failed' => []];
    $log = $this->openRunLog($runlog, count($sites));
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
      else {
        $summary['failed'][] = $site;
      }
    }
    $this->closeRunLog($log);

    $this->printSummary($io, $summary);
    if ($summary['failed']) {
      $io->error('Update failed for: ' . implode(', ', $summary['failed']));
      return Command::FAILURE;
    }
    $io->success('Updates completed for all sites.');
    return Command::SUCCESS;
  }

  /**
   * Open the retained per-site output log and write a run-start marker.
   *
   * @param string $runlog
   *   Path to the retained log (appended to).
   * @param int $count
   *   Number of sites in this run, recorded in the start marker.
   *
   * @return resource|null
   *   The open file handle, or NULL if it could not be opened, in which case
   *   output still streams to stdout and only the retained copy is lost.
   */
  private function openRunLog(string $runlog, int $count) {
    $log = @fopen($runlog, 'a');
    if ($log === FALSE) {
      return NULL;
    }
    fwrite($log, sprintf("===== START %s: %d site(s) =====\n", date('Y-m-d H:i:s'), $count));
    return $log;
  }

  /**
   * Write the run-end marker and close the retained output log.
   *
   * @param resource|null $log
   *   The handle from openRunLog(), or NULL if it could not be opened.
   */
  private function closeRunLog($log): void {
    if ($log === NULL) {
      return;
    }
    fwrite($log, sprintf("===== END %s =====\n\n", date('Y-m-d H:i:s')));
    fclose($log);
  }

  /**
   * Classify per-site outcomes from a parallel joblog.
   *
   * A site exits 0 when it was updated and SKIPPED when it was skipped; any
   * other non-zero code is a failure.
   *
   * @return array{updated: int, skipped: int, failed: string[]}
   *   Updated and skipped counts, and the names of failed sites.
   */
  private function classifyJoblog(string $joblog): array {
    $summary = ['updated' => 0, 'skipped' => 0, 'failed' => []];
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
      if ($exit === 0) {
        $summary['updated']++;
      }
      elseif ($exit === SiteUpdateCommand::SKIPPED) {
        $summary['skipped']++;
      }
      else {
        $parts = explode(' ', trim($cols[8]));
        $summary['failed'][] = end($parts);
      }
    }
    return $summary;
  }

  /**
   * Print the run summary: updated / skipped / failed counts.
   */
  private function printSummary(SymfonyStyle $io, array $summary): void {
    $io->writeln(sprintf(
      'Summary: %d updated, %d skipped, %d failed.',
      $summary['updated'],
      $summary['skipped'],
      count($summary['failed'])
    ));
  }

}
