<?php

namespace SiteNow\Command;

use SiteNow\Traits\ParsesListOptions;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Syncs every locally-enabled multisite from a remote environment to local.
 *
 * The BLT-independent replacement for `dsa` (drupal:sync:all-sites): the bulk
 * wrapper that runs site:sync once per site. The site list comes from the
 * uncommented "multisites" entries in blt/local.blt.yml (the same list
 * deploy:update reads locally), or from --sites. Unlike BLT's dsa, an empty
 * list syncs nothing rather than falling back to the entire fleet — a local
 * bulk sync clobbers every local database, so it must be asked for explicitly.
 *
 * Runs sequentially: local databases and remote sources should not be hit by
 * many parallel syncs at once.
 */
#[AsCommand(
  name: 'sync:all',
  description: '(ddev required) Sync every locally-enabled multisite from a remote environment to local.',
  aliases: ['dsa'],
)]
class SyncAllCommand extends Command {

  use SiteNowCommandsTrait;
  use ParsesListOptions;

  const ENVIRONMENTS = ['dev', 'test', 'prod'];

  /**
   * Exit code for a run that completed but had per-site failures.
   *
   * Distinct from FAILURE (1, the command itself could not run) so a caller
   * can tell "some sites failed" from "nothing ran".
   */
  const EXITCODE_PARTIAL = 2;

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates blt/local.blt.yml and the
   *   sn binary used for each site:sync.
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
      ->addOption('sites', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated site list to sync instead of the blt/local.blt.yml multisites list.', '')
      ->addOption('exclude', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated site domains to skip.', '')
      ->addOption('env', NULL, InputOption::VALUE_REQUIRED, 'Remote source environment: dev, test, or prod.', 'prod')
      ->addOption('sync-public-files', NULL, InputOption::VALUE_NONE, 'Also rsync each site\'s public files from the remote.')
      ->addOption('sync-private-files', NULL, InputOption::VALUE_NONE, 'Also rsync each site\'s private files from the remote.')
      ->addOption('no-update', NULL, InputOption::VALUE_NONE, 'Skip the post-sync update for each site: copy databases only.')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the confirmation prompt.')
      ->setHelp(<<<'HELP'
Syncs each locally-enabled site's remote database over its local one, in turn.

The site list is the uncommented "multisites" entries in blt/local.blt.yml,
unless --sites is given. An empty list syncs nothing (it will not fall back to
every site). Each site is handed to site:sync, so the same --env and file
options apply to every site in the run.

  # Sync every enabled site's prod database to local:
  ddev exec ./sn sync:all

  # From dev, database only, without editing local.blt.yml:
  ddev exec ./sn dsa --sites=brand.uiowa.edu,admissions.uiowa.edu --env=dev --no-update

  # Every enabled site except one, with public files:
  ddev exec ./sn dsa --exclude=huge.uiowa.edu --sync-public-files
HELP);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();

    $env = $input->getOption('env');
    if (!in_array($env, self::ENVIRONMENTS, TRUE)) {
      $err->error("Invalid environment '{$env}'. Must be one of: " . implode(', ', self::ENVIRONMENTS));
      return Command::FAILURE;
    }

    // Each site:sync reaches its remote over drush aliases + SSH, so gate the
    // whole run on the container and a forwarded agent up front.
    if (!$this->requireDdev($io, $this->getName())) {
      return Command::FAILURE;
    }
    if (!$this->requireSshAgent($io)) {
      return Command::FAILURE;
    }

    $exclude = $this->parseList($input->getOption('exclude'));
    $sites = array_values(array_diff($this->siteList($input), $exclude));

    if (!$sites) {
      $io->warning('No sites to sync. Uncomment entries under "multisites" in blt/local.blt.yml, or pass --sites=...');
      return Command::SUCCESS;
    }

    if (!$input->getOption('yes')) {
      $io->writeln(sprintf('The following %d site(s) will have their local database overwritten from %s:', count($sites), $env));
      $io->listing($sites);
      if (!$io->confirm('Continue?', FALSE)) {
        $io->writeln('Aborted.');
        return Command::FAILURE;
      }
    }

    // Options forwarded verbatim to each per-site site:sync. -y suppresses the
    // child's own prompt: the confirmation above covers the whole run, and the
    // children run non-interactively.
    $passthrough = ["--env={$env}", '-y', $output->isDecorated() ? '--ansi' : '--no-ansi'];
    foreach (['sync-public-files', 'sync-private-files', 'no-update'] as $flag) {
      if ($input->getOption($flag)) {
        $passthrough[] = "--{$flag}";
      }
    }

    $failed = [];
    $ok = 0;
    $total = count($sites);
    foreach ($sites as $i => $site) {
      $io->section(sprintf('[%d/%d] %s', $i + 1, $total, $site));
      $process = new Process(["{$this->repoRoot}/sn", 'site:sync', $site, ...$passthrough], $this->repoRoot);
      $process->setTimeout(NULL);
      $process->run(fn ($type, $buffer) => print $buffer);
      if ($process->isSuccessful()) {
        $ok++;
      }
      else {
        $failed[] = $site;
      }
    }

    $io->writeln('');
    $io->writeln(sprintf('Finished: %d synced, %d failed.', $ok, count($failed)));
    if ($failed) {
      $err->error('Sync failed for: ' . implode(', ', $failed));
      return self::EXITCODE_PARTIAL;
    }
    $io->success('All sites synced.');
    return Command::SUCCESS;
  }

  /**
   * Resolve the list of sites to sync.
   *
   * The --sites option overrides the file. Otherwise the list is the
   * uncommented "multisites" entries in blt/local.blt.yml; an absent or
   * all-commented key yields an empty list (no fleet-wide fallback).
   */
  private function siteList(InputInterface $input): array {
    $override = $this->parseList($input->getOption('sites'));
    if ($override) {
      return $override;
    }
    $local = "{$this->repoRoot}/blt/local.blt.yml";
    return is_file($local) ? (Yaml::parseFile($local)['multisites'] ?? []) : [];
  }

}
