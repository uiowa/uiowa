<?php

namespace SiteNow\Command;

use SiteNow\Install\InstallState;
use SiteNow\Install\InstallStatus;
use SiteNow\Process\ProcessPool;
use SiteNow\Traits\ClassifiesInstalls;
use SiteNow\Traits\DescribesDrushFailures;
use SiteNow\Traits\NotifiesSlack;
use SiteNow\Traits\ParsesListOptions;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Installs Drupal on the application's multisites that need it.
 *
 * Replaces the BLT `uiowa:multisite:install` (umi) command: scans the sites
 * this application owns, reports what each one needs, and fans site:install out
 * over the ones that need installing or healing.
 *
 * Where umi asked only whether a config table existed — which an install that
 * died partway already has — this separates a site that was never installed
 * from one whose install never finished, so an incomplete install gets picked
 * up on the next run instead of being skipped forever.
 *
 * Per-site output is captured to its own log file rather than interleaved: an
 * install is verbose, and a dozen of them at once is unreadable as a stream.
 */
#[AsCommand(
  name: 'multisite:install',
  description: "Install Drupal on the application's multisites that need it, healing incomplete installs.",
  aliases: ['umi'],
)]
class MultisiteInstallCommand extends Command {

  use ClassifiesInstalls;
  use DescribesDrushFailures;
  use NotifiesSlack;
  use ParsesListOptions;
  use SiteNowCommandsTrait;

  /**
   * Exit code for a run that completed but left sites needing attention.
   *
   * Distinct from FAILURE (1, the command itself could not run) so a caller can
   * tell "some sites failed" from "nothing ran".
   */
  const EXITCODE_PARTIAL = 2;

  /**
   * Environments installation is allowed on unless --envs says otherwise.
   *
   * Installing is a production activity here: dev and test get their databases
   * from prod, so installing there produces a site that the next database copy
   * overwrites.
   */
  const DEFAULT_ENVS = 'local,prod';

  /**
   * How long one site's install may take before it is abandoned, in seconds.
   *
   * An install runs the installer plus two config import passes, so it is slow
   * by nature; this only has to be long enough that a hung install is the only
   * thing it ever catches.
   */
  const TIMEOUT = 1800;

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates blt/manifest.yml, the sn
   *   binary used for each site:install, and each site's blt.yml.
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
      ->addOption('sites', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated site list to consider instead of the application default (testing / targeted recovery).', '')
      ->addOption('concurrency', 'j', InputOption::VALUE_REQUIRED, 'Number of sites to install in parallel.', '3')
      ->addOption('envs', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated environments installation is allowed on.', self::DEFAULT_ENVS)
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Report what each site needs without installing anything.')
      ->addOption('force', NULL, InputOption::VALUE_NONE, 'Reinstall incomplete installs even when they hold content. Destroys that content.')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the confirmation prompt.')
      ->setHelp(<<<'HELP'
Scans the sites this application owns and sorts them into four states:

  absent       Drupal was never installed. Gets installed.
  partial      An install started and never finished. Gets reinstalled, unless
               the site holds content — then it is reported, not touched.
  installed    A complete install. Left alone.
  unavailable  No site directory, or the database belongs to another
               application. Skipped.

Each site's full output goes to its own log file, named in the summary. Safe to
run repeatedly: a site that failed is picked up again by the next run.

Installing is limited to local and prod unless --envs says otherwise. A dry run
only reads, so it is allowed anywhere — use it on dev or test ahead of a release
to see what an environment would need.

Needs a database connection, so off Acquia it runs inside the container:
  ddev sn multisite:install

Examples:
  # What does this application need? Changes nothing.
  ./sn multisite:install --dry-run

  # Install everything that needs it, six at a time.
  ./sn multisite:install -j 6

  # One site, without waiting on a full scan.
  ./sn multisite:install --sites=new.uiowa.edu
HELP);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();
    $this->ansi = $output->isDecorated();

    $app = getenv('AH_SITE_GROUP') ?: 'local';
    $env = getenv('AH_SITE_ENVIRONMENT') ?: 'local';
    $is_acquia = (bool) getenv('AH_SITE_ENVIRONMENT');

    $dry_run = (bool) $input->getOption('dry-run');

    // A dry run only reads, so it is allowed on any environment. The gate is
    // about where a site may be installed, and being able to see what an
    // environment would need — dev or test ahead of a release — is the point of
    // having a dry run at all.
    $envs = $this->parseList($input->getOption('envs'));
    if (!$dry_run && !in_array($env, $envs, TRUE)) {
      $err->error("Installation is not allowed on the {$env} environment. Must be one of: " . implode(', ', $envs) . '. Use --envs to override.');
      return Command::FAILURE;
    }

    // Checked before anything is scanned. A short option takes its value with
    // no separator, so `-j=6` arrives as the string '=6', which casts to 0 and
    // would otherwise fall back to the default — after a scan of the whole
    // application, with nothing said about why it installs three at a time.
    $raw = trim((string) $input->getOption('concurrency'));
    if (!ctype_digit($raw) || (int) $raw < 1) {
      $err->error("Invalid --concurrency value '{$raw}'. Give a positive integer, as -j 6, -j6 or --concurrency=6.");
      return Command::FAILURE;
    }
    $concurrency = (int) $raw;

    // Every site's classification needs a database connection, and off Acquia
    // the database host only resolves inside the container.
    if (!$is_acquia && !$this->requireDdev($io, $this->getName())) {
      return Command::FAILURE;
    }

    // On Acquia the site list comes from the manifest, so a missing one must
    // fail rather than read as an empty fleet.
    $override = $this->parseList($input->getOption('sites'));
    if ($is_acquia && !$override && !$this->requireManifest($io)) {
      return Command::FAILURE;
    }

    $sites = $override ?: ($is_acquia ? $this->manifestSites($app) : $this->localMultisites());
    if (!$sites) {
      $io->warning("No sites to consider for application '{$app}'.");
      return Command::SUCCESS;
    }

    $force = (bool) $input->getOption('force');

    $where = $this->where($app, $env);
    $io->writeln(sprintf('Scanning %d site(s) on %s...', count($sites), $where));
    $states = $this->scan($io, $sites, $app, $is_acquia);

    ['targets' => $targets, 'blocked' => $blocked, 'counts' => $counts] = $this->selectTargets($states, $force);
    $this->reportScan($io, $targets, $blocked, $counts);

    if ($dry_run) {
      $io->writeln('Dry run: nothing was installed.');

      // Say so when the environment would have refused, so a dry run here is
      // not mistaken for permission to install here.
      if (!in_array($env, $envs, TRUE)) {
        $io->warning("Installing on {$env} is not allowed without --envs={$env}.");
      }

      return Command::SUCCESS;
    }

    if (!$targets) {
      $io->success('There are no sites needing installation.');
      return $blocked ? self::EXITCODE_PARTIAL : Command::SUCCESS;
    }

    if (!$input->getOption('yes')) {
      $question = sprintf('Install %d site(s) on %s?', count($targets), $where);
      if (!$io->confirm($question, FALSE)) {
        $io->writeln('Aborted.');
        return Command::FAILURE;
      }
    }

    $log_dir = $this->logDir();
    $io->writeln(sprintf('Installing %d site(s), %d at a time. Logs: %s', count($targets), $concurrency, $log_dir));
    $results = $this->runInstalls($io, $targets, $concurrency, $force, $log_dir);

    return $this->report($io, $where, $targets, $blocked, $results, $log_dir);
  }

  /**
   * Name where this is running, for a report line.
   *
   * @param string $app
   *   The application (AH_SITE_GROUP).
   * @param string $env
   *   The environment (AH_SITE_ENVIRONMENT).
   *
   * @return string
   *   The application and environment, collapsed to one word when both are
   *   'local' off Acquia.
   */
  private function where(string $app, string $env): string {
    return $app === $env ? $app : "{$app} {$env}";
  }

  /**
   * Classify every site, showing progress.
   *
   * Serial, because each site is one or two quick queries and the work worth
   * parallelizing is the installs that follow.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string[] $sites
   *   The site hosts to classify.
   * @param string $app
   *   The application (AH_SITE_GROUP).
   * @param bool $isAcquia
   *   Whether this is running on an Acquia environment.
   *
   * @return array<string, \SiteNow\Install\InstallState>
   *   States keyed by site host.
   */
  private function scan(SymfonyStyle $io, array $sites, string $app, bool $isAcquia): array {
    $states = [];

    // A progress bar only renders as one line on a terminal that can overwrite
    // it. Redirected to a log it becomes a line per site, ahead of the report
    // it was meant to precede, so it is left out there.
    $progress = $io->isDecorated();

    if ($progress) {
      $io->progressStart(count($sites));
    }

    foreach ($sites as $site) {
      $states[$site] = $this->classifyInstall($site, $app, $isAcquia);

      if ($progress) {
        $io->progressAdvance();
      }
    }

    if ($progress) {
      $io->progressFinish();
    }

    return $states;
  }

  /**
   * Sort classified sites by what each one needs.
   *
   * Three outcomes: install it, report it for a human, or leave it alone.
   *
   * @param array<string, \SiteNow\Install\InstallState> $states
   *   States keyed by site host.
   * @param bool $force
   *   Whether a partial install holding content should be reinstalled anyway.
   *
   * @return array{targets: array<string, \SiteNow\Install\InstallState>, blocked: array<string, \SiteNow\Install\InstallState>, counts: array{installed: int, unavailable: int}}
   *   Sites to install, sites needing a human, and counts of the rest.
   */
  protected function selectTargets(array $states, bool $force): array {
    $targets = [];
    $blocked = [];
    $counts = ['installed' => 0, 'unavailable' => 0];

    foreach ($states as $site => $state) {
      if ($state->status === InstallStatus::Installed) {
        $counts['installed']++;
      }
      elseif ($state->status === InstallStatus::Unavailable) {
        $counts['unavailable']++;
      }
      elseif ($state->status === InstallStatus::Partial && $state->hasContent() && !$force) {
        $blocked[$site] = $state;
      }
      else {
        $targets[$site] = $state;
      }
    }

    return ['targets' => $targets, 'blocked' => $blocked, 'counts' => $counts];
  }

  /**
   * Report what the scan found.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param array<string, \SiteNow\Install\InstallState> $targets
   *   Sites that will be installed.
   * @param array<string, \SiteNow\Install\InstallState> $blocked
   *   Sites needing a human.
   * @param array{installed: int, unavailable: int} $counts
   *   Counts of the sites needing nothing.
   */
  private function reportScan(SymfonyStyle $io, array $targets, array $blocked, array $counts): void {
    $io->writeln('');

    if ($targets) {
      $io->writeln(sprintf('<info>Needs installing (%d):</info>', count($targets)));
      foreach ($targets as $site => $state) {
        $io->writeln("  {$site} — {$state->describe()}");
      }
      $io->writeln('');
    }

    if ($blocked) {
      $io->writeln(sprintf('<comment>Needs a look (%d) — an unfinished install is not reinstalled when it holds content, or when that cannot be checked:</comment>', count($blocked)));
      foreach ($blocked as $site => $state) {
        $io->writeln("  {$site} — {$state->describe()}");
      }
      $io->writeln('  Re-run with --force to reinstall these anyway.');
      $io->writeln('');
    }

    $io->writeln(sprintf('Leaving alone: %d installed, %d unavailable.', $counts['installed'], $counts['unavailable']));
    $io->writeln('');
  }

  /**
   * Run the installs concurrently, one log file per site.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param array<string, \SiteNow\Install\InstallState> $targets
   *   Sites to install, keyed by host.
   * @param int $concurrency
   *   How many to run at once.
   * @param bool $force
   *   Whether to pass --force through to each install.
   * @param string $logDir
   *   Directory the per-site logs are written to.
   *
   * @return array<string, array{exit: int, output: string, error: string}>
   *   Per-site results keyed by host.
   */
  private function runInstalls(SymfonyStyle $io, array $targets, int $concurrency, bool $force, string $logDir): array {
    $jobs = [];
    foreach (array_keys($targets) as $site) {
      $jobs[$site] = array_values(array_filter([
        "{$this->repoRoot}/sn",
        'site:install',
        $site,
        // The output lands in a log file, where escape codes are noise.
        '--no-ansi',
        $force ? '--force' : NULL,
      ]));
    }

    // These jobs carry no group, so the per-group cap never applies; it is set
    // to the same total anyway rather than left looking like a forgotten
    // argument.
    //
    // No retries: a retried install would run the installer a second time,
    // against a site whose content was checked before the first attempt. Being
    // safe to re-run is what makes the next invocation of this command the
    // retry.
    $pool = new ProcessPool(
      concurrency: $concurrency,
      groupCap: $concurrency,
      timeout: self::TIMEOUT,
      retries: 0,
    );

    return $pool->run($jobs, [], function (int $done, int $total, ?string $site, ?array $result) use ($io, $targets, $logDir) {
      if ($site === NULL) {
        return;
      }

      $log = $this->writeLog($logDir, $site, $targets[$site], $result);
      $tier = $targets[$site]->status->value;

      if ($result['exit'] === Command::SUCCESS) {
        $io->writeln("<info>✔</info> [{$done}/{$total}] {$site} ({$tier} → installed)");
        return;
      }

      if ($result['exit'] === SiteInstallCommand::CONFIG_MISMATCH) {
        $io->writeln("<comment>!</comment> [{$done}/{$total}] {$site} ({$tier} → installed, config does not match)");
        return;
      }

      // A site can be reclassified between the scan and its turn to install —
      // most plausibly a content check that succeeded during the scan and fails
      // here, which the child answers with BLOCKED. Report what the child
      // actually decided rather than calling every non-zero exit a failure.
      if ($result['exit'] === SiteInstallCommand::BLOCKED) {
        $io->writeln("<comment>!</comment> [{$done}/{$total}] {$site} ({$tier}) needs a look: " . $this->failureReason($result));
        return;
      }

      if ($result['exit'] === SiteInstallCommand::SKIPPED) {
        $io->writeln("<comment>-</comment> [{$done}/{$total}] {$site} ({$tier}) skipped: no longer installable here");
        return;
      }

      $io->writeln("<error>✖</error> [{$done}/{$total}] {$site} ({$tier}) failed: " . $this->failureReason($result));
      if ($log !== NULL) {
        $io->writeln("      log: {$log}");
      }
    });
  }

  /**
   * Write one site's captured output to its own log file.
   *
   * @param string $logDir
   *   Directory the log is written to.
   * @param string $site
   *   The site host / canonical domain.
   * @param \SiteNow\Install\InstallState $state
   *   The state the site was in when it was picked up, recorded in the header.
   * @param array{exit: int, output: string, error: string} $result
   *   The finished process result.
   *
   * @return string|null
   *   The log path, or NULL when it could not be written — in which case only
   *   the retained copy is lost, not the reported outcome.
   */
  private function writeLog(string $logDir, string $site, InstallState $state, array $result): ?string {
    $path = "{$logDir}/{$site}.log";
    $body = sprintf(
      "===== %s — %s (%s) — exit %d =====\n%s\n%s",
      date('Y-m-d H:i:s'),
      $site,
      $state->describe(),
      $result['exit'],
      $result['output'],
      $result['error'],
    );

    return @file_put_contents($path, $body) === FALSE ? NULL : $path;
  }

  /**
   * The directory per-site install logs are written to.
   *
   * @return string
   *   The log directory, created if it did not exist. Falls back to the log
   *   directory's parent when it cannot be created, so a log write fails on its
   *   own rather than taking the run with it.
   */
  private function logDir(): string {
    $base = getenv('AH_SITE_ENVIRONMENT') ? '/shared/logs' : sys_get_temp_dir();
    $dir = "{$base}/sn_install";

    if (!is_dir($dir) && !@mkdir($dir, 0755, TRUE) && !is_dir($dir)) {
      return $base;
    }

    return $dir;
  }

  /**
   * Sort the finished installs into outcome tiers by what each child reported.
   *
   * A child reports its own outcome through its exit code, including the two
   * the scan did not predict: a site reclassified as needing a look (BLOCKED,
   * most plausibly a content check that succeeded during the scan and failed on
   * its turn) or as no longer installable here (SKIPPED). Those join the tiers
   * they belong to, so neither is announced as a failure — the tier decides
   * both the summary line and whether Slack is told at all.
   *
   * @param array<string, \SiteNow\Install\InstallState> $targets
   *   The sites that were run, keyed by host.
   * @param array<string, array{exit: int, output: string, error: string}> $results
   *   Per-site results keyed by host. A site missing from this counts as
   *   failed: it was run and reported nothing.
   * @param array<string, \SiteNow\Install\InstallState> $blocked
   *   Sites the scan already held back, which the results add to.
   *
   * @return array{installed: string[], mismatch: string[], failed: string[], skipped: string[], blocked: array<string, \SiteNow\Install\InstallState>}
   *   The outcome tiers.
   */
  protected function classifyResults(array $targets, array $results, array $blocked = []): array {
    $installed = [];
    $mismatch = [];
    $failed = [];
    $skipped = [];

    foreach ($targets as $site => $state) {
      $exit = $results[$site]['exit'] ?? Command::FAILURE;

      if ($exit === Command::SUCCESS) {
        $installed[] = $site;
      }
      elseif ($exit === SiteInstallCommand::CONFIG_MISMATCH) {
        $mismatch[] = $site;
      }
      elseif ($exit === SiteInstallCommand::BLOCKED) {
        $blocked[$site] = $state;
      }
      elseif ($exit === SiteInstallCommand::SKIPPED) {
        $skipped[] = $site;
      }
      else {
        $failed[] = $site;
      }
    }

    return [
      'installed' => $installed,
      'mismatch' => $mismatch,
      'failed' => $failed,
      'skipped' => $skipped,
      'blocked' => $blocked,
    ];
  }

  /**
   * Summarize the run, notify Slack, and decide the exit code.
   *
   * The per-site lines land in completion order, so the failures are collected
   * again here, in a stable order, as the part worth reading.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $where
   *   The application and environment this ran on, for the notification.
   * @param array<string, \SiteNow\Install\InstallState> $targets
   *   The sites that were installed, keyed by host.
   * @param array<string, \SiteNow\Install\InstallState> $blocked
   *   Sites left for a human.
   * @param array<string, array{exit: int, output: string, error: string}> $results
   *   Per-site results keyed by host.
   * @param string $logDir
   *   Directory the per-site logs were written to.
   *
   * @return int
   *   SUCCESS, or EXITCODE_PARTIAL when anything failed or needs a look.
   */
  private function report(SymfonyStyle $io, string $where, array $targets, array $blocked, array $results, string $logDir): int {
    [
      'installed' => $installed,
      'mismatch' => $mismatch,
      'failed' => $failed,
      'skipped' => $skipped,
      'blocked' => $blocked,
    ] = $this->classifyResults($targets, $results, $blocked);

    $io->writeln('');
    $io->writeln(sprintf(
      'Summary: %d installed, %d with config not matching, %d failed, %d needing a look.',
      count($installed),
      count($mismatch),
      count($failed),
      count($blocked),
    ));

    if ($skipped) {
      $io->writeln('Skipped, no longer installable here: ' . implode(', ', $skipped));
    }

    if ($failed) {
      $io->writeln('');
      $io->writeln('<error>Failures:</error>');

      // A site whose process never launched has no result to describe, and
      // still needs a line.
      $unknown = ['exit' => Command::FAILURE, 'output' => '', 'error' => ''];

      foreach ($failed as $site) {
        $io->writeln("  {$site} ({$targets[$site]->status->value})");
        $io->writeln('    ' . $this->failureReason($results[$site] ?? $unknown));

        // Only point at a log that is actually there; writing one is
        // best-effort.
        $log = "{$logDir}/{$site}.log";
        if (is_file($log)) {
          $io->writeln("    log: {$log}");
        }
      }
    }

    // Notify only when something needs attention. This is run by hand, so
    // whoever started it is already watching the output; a clean run announced
    // in Slack is noise that trains people to ignore the channel.
    $parts = [];
    if ($failed) {
      $parts[] = sprintf('FAILED on %d: %s', count($failed), implode(', ', $failed));
    }
    if ($mismatch) {
      $parts[] = sprintf('config does not match on %d: %s', count($mismatch), implode(', ', $mismatch));
    }
    if ($blocked) {
      $parts[] = sprintf('%d needing a look: %s', count($blocked), implode(', ', array_keys($blocked)));
    }

    if ($parts) {
      $message = sprintf(
        'Install on *%s*: %s. (%d installed.)',
        $where,
        implode('; ', $parts),
        count($installed),
      );
      $sent = $this->notifySlack($message, $failed ? ':rain_cloud:' : ':warning:');

      if ($sent !== NULL) {
        $io->getErrorStyle()->writeln("<comment>Slack notification skipped: {$sent}.</comment>");
      }
    }

    if ($failed || $blocked) {
      return self::EXITCODE_PARTIAL;
    }

    // A config mismatch is not a failed install — the sites are up — but it is
    // not a clean run either, so it does not get the success banner.
    if (!$mismatch) {
      $io->success('Installs completed.');
    }

    return Command::SUCCESS;
  }

}
