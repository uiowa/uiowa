<?php

namespace SiteNow\Command;

use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Uiowa\Multisite;

/**
 * Syncs a single multisite from a remote environment down to local.
 *
 * The BLT-independent replacement for `ds` (drupal:sync:default:site): copy the
 * remote database over the local one, rebuild caches, optionally rsync files,
 * then reconcile the copy via site:update (updatedb, config import, deploy
 * hooks). Runs inside DDEV and reaches the remote over drush aliases + SSH, so
 * it needs a forwarded SSH agent (`ddev auth ssh`).
 */
#[AsCommand(
  name: 'site:sync',
  description: '(ddev required) Sync a single multisite database (and optionally files) from a remote environment to local.',
  aliases: ['ds'],
)]
class SiteSyncCommand extends Command {

  use SiteNowCommandsTrait;

  const ENVIRONMENTS = ['dev', 'test', 'prod'];

  /**
   * File subdirectories skipped during file syncs.
   *
   * Derived assets (image-style derivatives, aggregated css/js) are rebuilt on
   * demand locally, so copying them wastes transfer. Mirrors the BLT
   * sync.exclude-paths default that `ds` used.
   */
  const FILE_EXCLUDE_PATHS = 'styles:css:js';

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates drush, the site alias
   *   files, and the sn binary used for the post-sync update.
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
      ->addArgument('site', InputArgument::REQUIRED, 'The site directory / canonical domain, e.g. brand.uiowa.edu.')
      ->addOption('env', NULL, InputOption::VALUE_REQUIRED, 'Remote source environment: dev, test, or prod.', 'prod')
      ->addOption('sync-public-files', NULL, InputOption::VALUE_NONE, 'Also rsync the public files directory from the remote.')
      ->addOption('sync-private-files', NULL, InputOption::VALUE_NONE, 'Also rsync the private files directory from the remote.')
      ->addOption('no-update', NULL, InputOption::VALUE_NONE, 'Skip the post-sync update (site:update): copy the database only.')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the confirmation prompt.')
      ->setHelp(<<<'HELP'
Copies a remote environment's database over your local one for a single site,
then reconciles it (drush deploy) so the local copy is usable.

  # Pull brand.uiowa.edu's prod database to local:
  ddev exec ./sn site:sync brand.uiowa.edu

  # Pull from dev instead, and bring public files too:
  ddev exec ./sn ds brand.uiowa.edu --env=dev --sync-public-files

  # Database only, no updatedb/config import afterward:
  ddev exec ./sn ds brand.uiowa.edu --no-update
HELP);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();

    $site = $input->getArgument('site');
    $env = $input->getOption('env');

    if (!in_array($env, self::ENVIRONMENTS, TRUE)) {
      $err->error("Invalid environment '{$env}'. Must be one of: " . implode(', ', self::ENVIRONMENTS));
      return Command::FAILURE;
    }

    // Reaches the remote site over drush aliases + SSH from inside the
    // container, so both the container and a forwarded agent are required.
    if (!$this->requireDdev($io, $this->getName())) {
      return Command::FAILURE;
    }
    if (!$this->requireSshAgent($io)) {
      return Command::FAILURE;
    }

    $id = Multisite::getIdentifier('http://' . $site);
    if (!is_file("{$this->repoRoot}/drush/sites/{$id}.site.yml")) {
      $err->error("No drush alias found for {$site} (expected {$id}.site.yml). Is the site provisioned?");
      return Command::FAILURE;
    }

    $remote = "@{$id}.{$env}";
    $local = "@{$id}.local";

    if (!$input->getOption('yes')) {
      $io->writeln("Sync <comment>{$remote}</comment> => <comment>{$local}</comment>");
      if (!$io->confirm("This overwrites your local database for {$site}. Continue?", FALSE)) {
        $io->writeln('Aborted.');
        return Command::FAILURE;
      }
    }

    // 1. Copy the remote database over the local one. --structure-tables-key
    //    skips transient table data (see sql.structure-tables in
    //    drush/drush.yml); --create-db drops and recreates the local database.
    $io->writeln("Copying database {$remote} => {$local}...");
    $sync = $this->drush([
      'sql-sync', $remote, $local,
      '--structure-tables-key=lightweight',
      '--create-db',
      '--target-dump=' . sys_get_temp_dir() . '/tmp.target.sql.gz',
      '--yes',
    ], TRUE);
    if (!$sync->isSuccessful()) {
      $err->error("Database sync failed for {$site}.");
      return Command::FAILURE;
    }

    // 2. Rebuild caches on the freshly copied local database.
    $this->drush([$local, 'cache:rebuild']);

    // 3. Optional file syncs, mirroring the old `ds --sync-*-files` behavior.
    //    The local destinations match where BLT's dsf/dspf wrote them.
    $dir = $this->siteDirectory($site);
    if ($input->getOption('sync-public-files')) {
      $io->writeln('Syncing public files...');
      $files = $this->drush([
        'rsync', "{$remote}:%files/", "{$this->repoRoot}/docroot/sites/{$dir}/files",
        '--exclude-paths=' . self::FILE_EXCLUDE_PATHS,
        '--yes',
      ], TRUE);
      if (!$files->isSuccessful()) {
        $err->error("Public file sync failed for {$site}.");
        return Command::FAILURE;
      }
    }
    if ($input->getOption('sync-private-files')) {
      $io->writeln('Syncing private files...');
      $private = $this->drush([
        'rsync', "{$remote}:%private/", "{$this->repoRoot}/files-private/{$dir}",
        '--exclude-paths=' . self::FILE_EXCLUDE_PATHS,
        '--yes',
      ], TRUE);
      if (!$private->isSuccessful()) {
        $err->error("Private file sync failed for {$site}.");
        return Command::FAILURE;
      }
    }

    // 4. Reconcile the copied database: updatedb, config import, deploy hooks.
    //    This is the "drupal:update" half of the old ds, delegated to the
    //    command that already owns it. A skip or config-mismatch exit is not a
    //    sync failure; only a genuine update error is.
    if (!$input->getOption('no-update')) {
      $update = new Process(["{$this->repoRoot}/sn", 'site:update', $site], $this->repoRoot);
      $update->setTimeout(NULL);
      $update->run(fn ($type, $buffer) => print $buffer);
      $tolerated = [Command::SUCCESS, SiteUpdateCommand::SKIPPED, SiteUpdateCommand::CONFIG_MISMATCH];
      if (!in_array($update->getExitCode(), $tolerated, TRUE)) {
        $err->error("Post-sync update failed for {$site}.");
        return Command::FAILURE;
      }
    }

    $io->success("Synced {$site} from {$env}.");
    return Command::SUCCESS;
  }

  /**
   * Resolve a host to its multisite directory via sites.php.
   *
   * Mirrors Drupal's own aliasing so file syncs land in the right directory:
   * sites.php maps alias hosts to a directory, and a host with no entry uses a
   * same-named directory.
   *
   * @param string $host
   *   The site host / canonical domain.
   *
   * @return string
   *   The multisite directory name.
   */
  protected function siteDirectory(string $host): string {
    $sites = [];
    $sites_file = "{$this->repoRoot}/docroot/sites/sites.php";
    if (is_file($sites_file)) {
      // sites.php populates $sites with host => directory aliases.
      include $sites_file;
    }
    return $sites[$host] ?? $host;
  }

  /**
   * Run a drush command and return the finished process.
   *
   * @param string[] $args
   *   Drush command and arguments (aliases included as leading elements).
   * @param bool $stream
   *   TRUE to stream output live (used for the long-running sync/rsync).
   *
   * @return \Symfony\Component\Process\Process
   *   The finished process.
   */
  private function drush(array $args, bool $stream = FALSE): Process {
    $process = new Process(
      ["{$this->repoRoot}/vendor/bin/drush", ...$args],
      $this->repoRoot,
    );
    $process->setTimeout(NULL);
    if ($stream) {
      $process->run(fn ($type, $buffer) => print $buffer);
    }
    else {
      $process->run();
    }
    return $process;
  }

}
