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

    $io->writeln(sprintf('Updating %d site(s) on %s, %d at a time.', count($sites), $app, $concurrency));

    if ($this->hasParallel()) {
      return $this->runParallel($io, $sites, $concurrency, $joblog);
    }
    return $this->runSequential($io, $sites);
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
    $sites = $manifest[$app] ?? [];
    // The default site lives on the uiowa application and must be updated too.
    if ($app === 'uiowa') {
      array_unshift($sites, 'default');
    }
    return $sites;
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
  private function runParallel(SymfonyStyle $io, array $sites, int $concurrency, string $joblog): int {
    $cmd = [
      'parallel',
      '-j', (string) $concurrency,
      '--joblog', $joblog,
      "{$this->repoRoot}/sn", 'site:update', '{}',
      ':::', ...$sites,
    ];

    $process = new Process($cmd, $this->repoRoot);
    $process->setTimeout(NULL);
    $process->run(fn($type, $buffer) => print $buffer);

    // GNU parallel exits non-zero when any job failed; the joblog records the
    // per-site outcome regardless.
    if ($process->getExitCode() !== 0) {
      $failed = $this->failedSites($joblog);
      $io->error('Update failed for: ' . ($failed ? implode(', ', $failed) : 'see ' . $joblog));
      return Command::FAILURE;
    }

    $io->success('Updates completed for all sites.');
    return Command::SUCCESS;
  }

  /**
   * Run updates one site at a time (off Acquia, where parallel may be absent).
   */
  private function runSequential(SymfonyStyle $io, array $sites): int {
    $failed = [];
    foreach ($sites as $site) {
      $process = new Process(["{$this->repoRoot}/sn", 'site:update', $site], $this->repoRoot);
      $process->setTimeout(NULL);
      $process->run(fn($type, $buffer) => print $buffer);
      if (!$process->isSuccessful()) {
        $failed[] = $site;
      }
    }

    if ($failed) {
      $io->error('Update failed for: ' . implode(', ', $failed));
      return Command::FAILURE;
    }
    $io->success('Updates completed for all sites.');
    return Command::SUCCESS;
  }

  /**
   * Read the failed site names from a parallel joblog.
   *
   * @return string[]
   *   Site names whose job exited non-zero.
   */
  private function failedSites(string $joblog): array {
    if (!is_file($joblog)) {
      return [];
    }
    $failed = [];
    foreach (file($joblog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
      // Skip the header row.
      if ($i === 0) {
        continue;
      }
      $cols = explode("\t", $line);
      // Columns 6 and 8 are Exitval and Command (the site is the last token).
      if (isset($cols[6]) && (int) $cols[6] !== 0 && isset($cols[8])) {
        $parts = explode(' ', trim($cols[8]));
        $failed[] = end($parts);
      }
    }
    return $failed;
  }

}
