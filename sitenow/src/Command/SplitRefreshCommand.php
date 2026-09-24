<?php

namespace SiteNow\Command;

use Composer\Autoload\ClassLoader;
use SiteNow\Config\Splits;
use SiteNow\Process\ProcessPool;
use SiteNow\Traits\ParsesListOptions;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Re-exports config splits from freshly synced remote databases.
 */
#[AsCommand(
  name: 'split:refresh',
  description: 'Re-export config splits from freshly synced remote databases.',
)]
class SplitRefreshCommand extends Command implements SignalableCommandInterface {

  use ParsesListOptions;
  use SiteNowCommandsTrait;

  /**
   * Seconds allowed for one site sync.
   */
  const SYNC_TIMEOUT = 7200;

  /**
   * Seconds allowed for one site update or split export.
   */
  const UPDATE_TIMEOUT = 3600;

  /**
   * Autoload prefixes preloaded before any branch switch.
   *
   * A branch switch followed by composer install replaces vendor/ and
   * sitenow/src on disk.
   */
  const PRELOAD_PREFIXES = [
    'SiteNow\\',
    'Symfony\\Component\\Console\\',
    'Symfony\\Component\\Filesystem\\',
    'Symfony\\Component\\Finder\\',
    'Symfony\\Component\\Process\\',
    'Symfony\\Component\\String\\',
    'Symfony\\Component\\Yaml\\',
    'Symfony\\Contracts\\Service\\',
  ];

  /**
   * The site:update exit codes that count as updated.
   *
   * A config mismatch is expected: the split export that follows is what
   * brings the files in line.
   */
  const UPDATED = [Command::SUCCESS, SiteUpdateCommand::CONFIG_MISMATCH];

  /**
   * The container path of the repository root.
   */
  const CONTAINER_ROOT = '/var/www/html';

  /**
   * The output style.
   */
  private SymfonyStyle $io;

  /**
   * The branch the command was started on.
   */
  private string $branch = '';

  /**
   * Whether the checkout is currently switched away from $branch.
   */
  private bool $switched = FALSE;

  /**
   * Whether DDEV syncs the project into the container with Mutagen.
   */
  private bool $mutagen = FALSE;

  /**
   * Files loaded before the first branch switch.
   *
   * @var string[]
   */
  private array $baseline = [];

  /**
   * The run's directory, relative to the repository root.
   */
  private string $runDir = '';

  /**
   * Per-target results, keyed by target label then stage.
   *
   * @var array<string, array<string, string>>
   */
  private array $results = [];

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
      ->addOption('split', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated feature split IDs to refresh (e.g. event,thesis_defense).', '')
      ->addOption('sites', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated hosts whose site splits to refresh.', '')
      ->addOption('base', NULL, InputOption::VALUE_REQUIRED, 'The branch whose code matches production.', 'main')
      ->addOption('env', NULL, InputOption::VALUE_REQUIRED, 'Remote source environment: dev, test, or prod.', 'prod')
      ->addOption('concurrency', 'j', InputOption::VALUE_REQUIRED, 'Number of site operations to run in parallel.', '4')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the confirmation prompt.')
      ->setHelp(<<<'HELP'
Syncs remote databases (prod by default) and re-exports config splits from
them, so the exports reflect what running this branch's updates against those
databases produces. Run it on the host shell, on the branch that will receive the
exports, with no uncommitted changes to tracked files.

With no --split or --sites, every feature split and every site split is
refreshed. Each target overwrites a local database: the default site's for
feature splits, the host's own for site splits.

When the branch has commits the base branch lacks, databases are synced and
feature splits activated with the base branch checked out, then updated and
exported with this branch checked out. A feature split the base branch does
not define is activated after the update instead. The command switches branches and
runs composer install for each phase, and returns to this branch even if it
fails or is interrupted.

  # Everything, unattended:
  ./sn split:refresh --yes

  # One feature split and one site split:
  ./sn split:refresh --split=thesis_defense --sites=grad.uiowa.edu

Nothing is committed. Review the report, then the diff, before committing.
HELP);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscribedSignals(): array {
    return defined('SIGINT') ? [SIGINT, SIGTERM] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false {
    if (isset($this->io)) {
      $this->io->newLine();
      $this->io->warning('Interrupted.');
    }
    $this->restoreBranch();
    return 130;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->io = new SymfonyStyle($input, $output);
    $this->ansi = $output->isDecorated();
    $err = $this->io->getErrorStyle();

    $base = $input->getOption('base');
    $env = $input->getOption('env');

    if (!$this->isHostShell()) {
      $err->error('Run this on the host shell, not inside DDEV: ./sn split:refresh');
      return Command::FAILURE;
    }
    if (!$this->requireEnvironment($this->io, $env)) {
      return Command::FAILURE;
    }
    $raw = trim((string) $input->getOption('concurrency'));
    if (!ctype_digit($raw) || (int) $raw < 1) {
      $err->error("Invalid --concurrency value '{$raw}'. Give a positive integer, as -j 6, -j6 or --concurrency=6.");
      return Command::FAILURE;
    }
    $concurrency = (int) $raw;

    $this->branch = $this->currentBranch();
    if ($this->branch === '') {
      $err->error('Check out a branch first. The command returns to it after switching.');
      return Command::FAILURE;
    }
    if ($this->branch === $base) {
      $err->error("Create a branch for the exports first, e.g. git switch -c config-split-refresh. The command does not run on {$base}.");
      return Command::FAILURE;
    }
    if (!$this->git(['rev-parse', '--verify', '--quiet', "{$base}^{commit}"])->isSuccessful()) {
      $err->error("Base branch {$base} does not exist.");
      return Command::FAILURE;
    }
    $dirty = trim($this->git(['status', '--porcelain', '--untracked-files=no'])->getOutput());
    if ($dirty !== '') {
      $err->error("Commit or discard changes to tracked files first. The command switches branches.\n\n{$dirty}");
      return Command::FAILURE;
    }

    $splits = new Splits($this->repoRoot);
    $features = $splits->features();
    $sites = $splits->sites();
    $only_splits = $this->parseList($input->getOption('split'));
    $only_sites = $this->parseList($input->getOption('sites'));
    $unknown = [
      ...array_diff($only_splits, array_keys($features)),
      ...array_diff($only_sites, array_keys($sites)),
    ];
    if ($unknown) {
      $err->error('Unknown split or site: ' . implode(', ', $unknown));
      return Command::FAILURE;
    }
    if ($only_splits || $only_sites) {
      $features = array_intersect_key($features, array_flip($only_splits));
      $sites = array_intersect_key($sites, array_flip($only_sites));
    }

    if (!$this->requireDdevRunning()) {
      return Command::FAILURE;
    }
    if (!$this->ddevExec(['ssh-add', '-l'])->isSuccessful()) {
      $err->error('The DDEV container has no SSH agent keys. Run ddev auth ssh first.');
      return Command::FAILURE;
    }

    $two_phase = (int) trim($this->git(['rev-list', '--count', "{$base}..HEAD"])->getOutput()) > 0;

    $this->io->title('Refresh config splits');
    $this->io->listing(array_filter([
      $features ? count($features) . ' feature split(s), exported from the default site' : NULL,
      $sites ? count($sites) . ' site split(s)' : NULL,
      $two_phase
        ? "Databases prepared on {$base}, then updated and exported on {$this->branch}"
        : "{$this->branch} has no commits beyond {$base}: prepared and exported without switching",
    ]));
    if (!$input->getOption('yes')) {
      $databases = count($sites) + ($features ? 1 : 0);
      if (!$this->io->confirm("This overwrites {$databases} local database(s). Continue?", FALSE)) {
        return Command::FAILURE;
      }
    }

    $this->runDir = 'tmp/split-refresh/' . date('Ymd-His');
    @mkdir("{$this->repoRoot}/{$this->runDir}/logs", 0777, TRUE);

    // Local settings generated by the base branch may not load on this one,
    // and a sync only generates the file when it is missing.
    $hosts = [...array_keys($sites), ...($features ? ['default'] : [])];
    $this->ensureLocalSettings($hosts);

    $this->preload();

    try {
      // The sync boots each local site before copying over it, and a database
      // a newer branch has updated may not boot on the base branch's code.
      $this->emptyDatabases($hosts);
      if ($two_phase) {
        $this->assertNoNewCode();
        $this->switchTo($base);
      }
      $this->prepare($features, $sites, $env, $concurrency, $two_phase ? $base : NULL);
      $this->assertNoNewCode();
      if ($two_phase) {
        $this->switchTo($this->branch);
      }
      $this->export($features, $sites, $concurrency);
      $this->assertNoNewCode();
    }
    catch (\RuntimeException $e) {
      $err->error($e->getMessage());
      $this->restoreBranch();
      $this->syncFiles();
      $this->report($features, $sites);
      return Command::FAILURE;
    }

    $this->syncFiles();
    return $this->report($features, $sites) ? Command::SUCCESS : Command::FAILURE;
  }

  /**
   * Sync databases and prepare the feature split snapshots.
   *
   * Every host with a site split is synced. The default site is synced once,
   * dumped, and each feature split is activated on a fresh copy of that dump
   * and snapshotted.
   *
   * @param array<string, string> $features
   *   Feature split folders keyed by ID.
   * @param array<string, string> $sites
   *   Site split folders keyed by host.
   * @param string $env
   *   The remote environment to sync from.
   * @param int $concurrency
   *   How many syncs to run at once.
   * @param string|null $base
   *   The base branch checked out, or NULL when running without switching.
   */
  private function prepare(array $features, array $sites, string $env, int $concurrency, ?string $base): void {
    $this->io->section('Sync databases');
    $hosts = [...array_keys($sites), ...($features ? ['default'] : [])];
    $jobs = [];
    foreach ($hosts as $host) {
      $jobs[$host] = $this->snJob(['site:sync', $host, "--env={$env}", '--no-update', '--yes']);
    }
    $synced = $this->pool($jobs, 'sync', self::SYNC_TIMEOUT, $concurrency, $this->appGroups($hosts));
    foreach (array_keys($sites) as $host) {
      $this->results[$host]['sync'] = $synced[$host] ? 'ok' : 'failed';
    }

    if (!$features) {
      return;
    }
    if (!$synced['default']) {
      foreach (array_keys($features) as $id) {
        $this->results["{$id} (feature)"]['sync'] = 'failed';
      }
      return;
    }

    $this->io->section('Activate feature splits');
    $db = self::CONTAINER_ROOT . "/{$this->runDir}/db";
    $this->ddevExec(['mkdir', '-p', $db]);
    if (!$this->step('default', ['sql:dump', '--gzip', "--result-file={$db}/base.sql"], 'dump-base')) {
      throw new \RuntimeException('Could not dump the synced default site. See the dump-base log.');
    }
    $splits = new Splits($this->repoRoot);
    foreach (array_keys($features) as $id) {
      $label = "{$id} (feature)";
      $this->results[$label]['sync'] = 'ok';
      if ($base !== NULL && !$this->definedOn($base, $splits->activationOrder($id))) {
        $this->results[$label]['activate'] = 'after update';
        continue;
      }
      $ok = $this->loadDatabase("{$db}/base.sql.gz", "activate-{$id}");
      foreach ($splits->activationOrder($id) as $split) {
        $ok = $ok && $this->step('default', ['config-split:activate', $split, '--yes'], "activate-{$id}");
      }
      $ok = $ok && $this->step('default', ['sql:dump', '--gzip', "--result-file={$db}/{$id}.sql"], "activate-{$id}");
      $this->results[$label]['activate'] = $ok ? 'ok' : 'failed';
      $this->progress($ok, 'activate', $id);
    }
  }

  /**
   * Update the prepared databases and export their splits.
   *
   * Each synced host is updated, then its site split exported. Each feature
   * snapshot is loaded into the default site, updated, then its split exported.
   *
   * @param array<string, string> $features
   *   Feature split folders keyed by ID.
   * @param array<string, string> $sites
   *   Site split folders keyed by host.
   * @param int $concurrency
   *   How many site operations to run at once.
   */
  private function export(array $features, array $sites, int $concurrency): void {
    $ready = array_filter(array_keys($sites), fn ($host) => $this->results[$host]['sync'] === 'ok');
    if ($ready) {
      $this->io->section('Update and export site splits');
      $jobs = [];
      foreach ($ready as $host) {
        $jobs[$host] = $this->snJob(['site:update', $host]);
      }
      $updated = $this->pool($jobs, 'update', self::UPDATE_TIMEOUT, $concurrency, [], self::UPDATED);
      $jobs = [];
      foreach ($ready as $host) {
        $this->results[$host]['update'] = $updated[$host] ? 'ok' : 'failed';
        if ($updated[$host]) {
          $jobs[$host] = $this->drushJob($host, ['config-split:export', 'site', '--yes']);
        }
      }
      $exported = $this->pool($jobs, 'export', self::UPDATE_TIMEOUT, $concurrency);
      foreach (array_keys($jobs) as $host) {
        $this->results[$host]['export'] = $exported[$host] ? 'ok' : 'failed';
      }
    }

    $ready = array_filter(
      array_keys($features),
      fn ($id) => in_array($this->results["{$id} (feature)"]['activate'] ?? '', ['ok', 'after update'], TRUE),
    );
    if ($ready) {
      $this->io->section('Update and export feature splits');
      $db = self::CONTAINER_ROOT . "/{$this->runDir}/db";
      $splits = new Splits($this->repoRoot);
      foreach ($ready as $id) {
        $label = "{$id} (feature)";
        $deferred = $this->results[$label]['activate'] === 'after update';
        $ok = $this->loadDatabase($deferred ? "{$db}/base.sql.gz" : "{$db}/{$id}.sql.gz", "update-{$id}");
        $ok = $ok && $this->runLogged($this->snJob(['site:update', 'default']), "update-{$id}", self::UPDATED);
        foreach ($deferred ? $splits->activationOrder($id) : [] as $split) {
          $ok = $ok && $this->step('default', ['config-split:activate', $split, '--yes'], "update-{$id}");
        }
        $this->results[$label]['update'] = $ok ? 'ok' : 'failed';
        if (!$ok) {
          $this->progress(FALSE, 'update', $id);
          continue;
        }
        $ok = $this->step('default', ['config-split:export', $id, '--yes'], "export-{$id}");
        $this->results[$label]['export'] = $ok ? 'ok' : 'failed';
        $this->progress($ok, 'export', $id);
      }
    }
  }

  /**
   * Print the results and the changes left for review.
   *
   * @param array<string, string> $features
   *   Feature split folders keyed by ID.
   * @param array<string, string> $sites
   *   Site split folders keyed by host.
   *
   * @return bool
   *   TRUE when every target exported.
   */
  private function report(array $features, array $sites): bool {
    $this->io->section('Results');
    $rows = [];
    $all_ok = TRUE;
    foreach ($this->results as $label => $stages) {
      $all_ok = $all_ok && ($stages['export'] ?? '') === 'ok';
      $rows[] = [
        $label,
        $stages['sync'] ?? '-',
        $stages['activate'] ?? '-',
        $stages['update'] ?? '-',
        $stages['export'] ?? '-',
      ];
    }
    $this->io->table(['Target', 'Sync', 'Activate', 'Update', 'Export'], $rows);

    $folders = [];
    foreach ($features as $id => $folder) {
      $folders[$folder] = "{$id} (feature)";
    }
    foreach ($sites as $host => $folder) {
      $folders[$folder] = $host;
    }
    $changes = $this->configChanges();
    $grouped = [];
    $outside = [];
    foreach ($changes as $path) {
      $owner = NULL;
      foreach (array_keys($folders) as $folder) {
        if (str_starts_with($path, "{$folder}/")) {
          $owner = $folders[$folder];
          break;
        }
      }
      if ($owner === NULL) {
        $outside[] = $path;
      }
      else {
        $grouped[$owner][] = $path;
      }
    }

    $this->io->section('Changes to review');
    if (!$changes) {
      $this->io->writeln('No config changes.');
    }
    ksort($grouped);
    foreach ($grouped as $owner => $paths) {
      $this->io->writeln("<info>{$owner}</info>");
      $this->io->listing($paths);
    }
    if ($outside) {
      $this->io->warning("Changed outside the refreshed splits' folders:\n  " . implode("\n  ", $outside));
    }
    $orphans = $this->orphanPatches($changes);
    if ($orphans) {
      $this->io->warning("Patches for config that is not tracked anywhere in config/. These usually carry one site's local config into a shared split:\n  " . implode("\n  ", $orphans));
    }

    $this->io->writeln("Logs: {$this->runDir}/logs");
    return $all_ok;
  }

  /**
   * Recreate each site's local database, empty.
   *
   * @param string[] $hosts
   *   The hosts.
   */
  private function emptyDatabases(array $hosts): void {
    foreach ($hosts as $host) {
      if (!$this->runLogged($this->drushJob($host, ['sql:create', '--yes']), "empty-{$host}")) {
        throw new \RuntimeException("Could not recreate the local database for {$host}. See the empty-{$host} log.");
      }
    }
  }

  /**
   * Generate local settings for sites that lack them.
   *
   * @param string[] $hosts
   *   The hosts to check.
   */
  private function ensureLocalSettings(array $hosts): void {
    foreach ($hosts as $host) {
      if (!is_file($this->localSettingsFile($host))) {
        $this->runLogged(['ddev', 'exec', '--', self::CONTAINER_ROOT . '/vendor/bin/drush', "--uri={$host}", 'settings'], "settings-{$host}");
      }
    }
  }

  /**
   * Check out a branch and install its dependencies.
   *
   * @param string $ref
   *   The branch to check out.
   */
  private function switchTo(string $ref): void {
    $this->io->section("Switch to {$ref}");
    $checkout = $this->git(['checkout', '--quiet', $ref]);
    if (!$checkout->isSuccessful()) {
      throw new \RuntimeException("git checkout {$ref} failed:\n" . $checkout->getErrorOutput());
    }
    $this->switched = $ref !== $this->branch;
    $this->syncFiles();
    if (!$this->runLogged(['ddev', 'composer', 'install', '--no-interaction', '--no-progress'], "composer-{$ref}")) {
      throw new \RuntimeException("composer install failed on {$ref}. See the composer-{$ref} log.");
    }
    $this->syncFiles();
  }

  /**
   * Return to the starting branch if the command switched away from it.
   */
  private function restoreBranch(): void {
    if (!$this->switched) {
      return;
    }
    $this->io->writeln("Returning to {$this->branch}...");
    try {
      $this->switchTo($this->branch);
    }
    catch (\RuntimeException $e) {
      $this->io->error("Could not return to {$this->branch}: {$e->getMessage()}\nRun: git checkout {$this->branch} && ddev composer install");
    }
  }

  /**
   * Load every class the command can reach from the current checkout.
   */
  private function preload(): void {
    set_error_handler(fn () => TRUE, E_DEPRECATED | E_USER_DEPRECATED);
    foreach (ClassLoader::getRegisteredLoaders() as $loader) {
      foreach ($loader->getPrefixesPsr4() as $prefix => $dirs) {
        if (!in_array($prefix, self::PRELOAD_PREFIXES, TRUE)) {
          continue;
        }
        foreach ($dirs as $dir) {
          $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
          foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen(rtrim($dir, '/')) + 1);
            // Only class files. Loading a functions file such as
            // Resources/functions.php a second time is a fatal redeclaration.
            if (!preg_match('#^([A-Z][A-Za-z0-9]*/)*[A-Z][A-Za-z0-9]*\.php$#', $relative) || preg_match('#(^|/)(Resources|Tests)/#', $relative)) {
              continue;
            }
            $class = $prefix . strtr(substr($relative, 0, -4), '/', '\\');
            try {
              class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
            }
            catch (\Throwable) {
              // A class extending an optional dependency that is not installed.
              // Nothing here can reach it.
            }
          }
        }
      }
    }
    restore_error_handler();
    $this->baseline = get_included_files();
  }

  /**
   * Stop if code was loaded after preloading.
   */
  private function assertNoNewCode(): void {
    $new = array_filter(
      array_diff(get_included_files(), $this->baseline),
      fn ($file) => str_starts_with($file, "{$this->repoRoot}/vendor/") || str_starts_with($file, "{$this->repoRoot}/sitenow/"),
    );
    if ($new) {
      throw new \RuntimeException("Code was loaded after preloading, so it may come from the other branch. Add its prefix to PRELOAD_PREFIXES:\n  " . implode("\n  ", $new));
    }
  }

  /**
   * Run jobs through a process pool, logging each one.
   *
   * @param array<string, string[]> $jobs
   *   Argv arrays keyed by target.
   * @param string $stage
   *   The stage name, used for progress lines and log names.
   * @param int $timeout
   *   Per-job timeout in seconds.
   * @param int $concurrency
   *   Maximum simultaneous jobs.
   * @param array<string, string> $groups
   *   Per-job group names, for the pool's per-group cap.
   * @param int[] $success
   *   Exit codes that count as success.
   *
   * @return array<string, bool>
   *   Whether each job succeeded, keyed by target.
   */
  private function pool(array $jobs, string $stage, int $timeout, int $concurrency, array $groups = [], array $success = [Command::SUCCESS]): array {
    if (!$jobs) {
      return [];
    }
    $pool = new ProcessPool($concurrency, 8, $timeout);
    $results = $pool->run($jobs, $groups, function (int $done, int $total, ?string $key, ?array $result) use ($stage, $success) {
      if ($key !== NULL) {
        $this->log("{$stage}-{$key}", $result['output'] . $result['error']);
        $this->progress(in_array($result['exit'], $success, TRUE), $stage, $key, "{$done}/{$total}");
      }
    });
    return array_map(fn (array $result) => in_array($result['exit'], $success, TRUE), $results);
  }

  /**
   * Run one drush command against a local site.
   *
   * @param string $host
   *   The site host.
   * @param string[] $args
   *   Drush arguments.
   * @param string $log
   *   The log name.
   *
   * @return bool
   *   TRUE on success.
   */
  private function step(string $host, array $args, string $log): bool {
    return $this->runLogged($this->drushJob($host, $args), $log);
  }

  /**
   * Replace the default site's database with a dump.
   *
   * @param string $dump
   *   Container path of a gzipped dump.
   * @param string $log
   *   The log name.
   *
   * @return bool
   *   TRUE on success.
   */
  private function loadDatabase(string $dump, string $log): bool {
    return $this->step('default', ['sql:drop', '--yes'], $log)
      && $this->step('default', ['sql:query', "--file={$dump}"], $log);
  }

  /**
   * Run one process to completion, appending its output to a log.
   *
   * @param string[] $argv
   *   The command.
   * @param string $log
   *   The log name.
   * @param int[] $success
   *   Exit codes that count as success.
   *
   * @return bool
   *   TRUE when the exit code is in $success.
   */
  private function runLogged(array $argv, string $log, array $success = [Command::SUCCESS]): bool {
    $process = new Process($argv, $this->repoRoot);
    $process->setTimeout(self::UPDATE_TIMEOUT);
    $process->run();
    $this->log($log, '$ ' . implode(' ', $argv) . "\n" . $process->getOutput() . $process->getErrorOutput());
    return in_array($process->getExitCode(), $success, TRUE);
  }

  /**
   * Build the argv for an sn command inside the container.
   *
   * @param string[] $args
   *   The sn arguments.
   *
   * @return string[]
   *   The argv.
   */
  private function snJob(array $args): array {
    return ['ddev', 'exec', '--', './sn', ...$args, '--no-ansi'];
  }

  /**
   * Build the argv for a drush command against a local site.
   *
   * @param string $host
   *   The site host.
   * @param string[] $args
   *   The drush arguments.
   *
   * @return string[]
   *   The argv.
   */
  private function drushJob(string $host, array $args): array {
    return [
      'ddev', 'exec', '--', self::CONTAINER_ROOT . '/vendor/bin/drush',
      "@{$this->getDrushAlias($host)}.local",
      ...$args,
    ];
  }

  /**
   * Run a command inside the container.
   *
   * @param string[] $args
   *   The command and its arguments.
   *
   * @return \Symfony\Component\Process\Process
   *   The finished process.
   */
  private function ddevExec(array $args): Process {
    $process = new Process(['ddev', 'exec', '--', ...$args], $this->repoRoot);
    $process->run();
    return $process;
  }

  /**
   * Check that a branch defines every given split.
   *
   * @param string $ref
   *   The branch.
   * @param string[] $ids
   *   Split IDs.
   *
   * @return bool
   *   TRUE when the branch has a definition for each split.
   */
  private function definedOn(string $ref, array $ids): bool {
    foreach ($ids as $id) {
      if (!$this->git(['cat-file', '-e', "{$ref}:config/default/config_split.config_split.{$id}.yml"])->isSuccessful()) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Run git on the host.
   *
   * @param string[] $args
   *   The git arguments.
   *
   * @return \Symfony\Component\Process\Process
   *   The finished process.
   */
  private function git(array $args): Process {
    $process = new Process(['git', ...$args], $this->repoRoot);
    $process->run();
    return $process;
  }

  /**
   * Require DDEV to be running, and note how it syncs files.
   *
   * @return bool
   *   TRUE when DDEV is running.
   */
  private function requireDdevRunning(): bool {
    $describe = new Process(['ddev', 'describe', '-j'], $this->repoRoot);
    $describe->run();
    $raw = json_decode($describe->getOutput(), TRUE)['raw'] ?? [];
    if (($raw['status'] ?? '') !== 'running') {
      $this->io->getErrorStyle()->error('DDEV is not running. Run ddev start first.');
      return FALSE;
    }
    $this->mutagen = !empty($raw['mutagen_enabled']);
    return TRUE;
  }

  /**
   * Flush Mutagen's sync between the host and the container.
   */
  private function syncFiles(): void {
    if ($this->mutagen) {
      (new Process(['ddev', 'mutagen', 'sync'], $this->repoRoot))->setTimeout(600)->run();
    }
  }

  /**
   * Group hosts by Acquia application, for the per-application SSH cap.
   *
   * @param string[] $hosts
   *   The hosts.
   *
   * @return array<string, string>
   *   Application names keyed by host, for hosts in the manifest.
   */
  private function appGroups(array $hosts): array {
    $groups = [];
    foreach ($this->manifest() as $app => $app_hosts) {
      foreach (array_intersect($hosts, $app_hosts) as $host) {
        $groups[$host] = $app;
      }
    }
    return $groups;
  }

  /**
   * List changed and untracked files under config/.
   *
   * @return string[]
   *   Repository-relative paths.
   */
  private function configChanges(): array {
    $paths = [];
    $status = $this->git(['status', '--porcelain', '--untracked-files=all', '--', 'config/'])->getOutput();
    foreach (array_filter(explode("\n", $status)) as $line) {
      $path = substr($line, 3);
      $paths[] = str_contains($path, ' -> ') ? explode(' -> ', $path)[1] : $path;
    }
    return $paths;
  }

  /**
   * Find split patches whose target config is not tracked anywhere.
   *
   * @param string[] $paths
   *   Changed paths.
   *
   * @return string[]
   *   The orphaned patch paths.
   */
  private function orphanPatches(array $paths): array {
    $tracked = array_flip(array_map('basename', array_filter(explode("\n", $this->git(['ls-files', '--', 'config/'])->getOutput()))));
    return array_values(array_filter($paths, function (string $path) use ($tracked) {
      return preg_match('#/config_split\.patch\.(.+\.yml)$#', $path, $match) && !isset($tracked[$match[1]]);
    }));
  }

  /**
   * Print one progress line.
   *
   * @param bool $ok
   *   Whether the step succeeded.
   * @param string $stage
   *   The stage name.
   * @param string $target
   *   The target.
   * @param string $count
   *   Optional progress count, e.g. 3/40.
   */
  private function progress(bool $ok, string $stage, string $target, string $count = ''): void {
    $mark = $ok ? '<info>✓</info>' : '<error>✗</error>';
    $suffix = $ok ? '' : " (see logs/{$stage}-{$target}.log)";
    $this->io->writeln(trim("{$mark} {$stage} {$target} {$count}") . $suffix);
  }

  /**
   * Append output to a run log.
   *
   * @param string $name
   *   The log name, without extension.
   * @param string $text
   *   The text to append.
   */
  private function log(string $name, string $text): void {
    file_put_contents("{$this->repoRoot}/{$this->runDir}/logs/{$name}.log", $text . "\n", FILE_APPEND);
  }

}
