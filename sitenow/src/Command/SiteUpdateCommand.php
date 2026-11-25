<?php

namespace SiteNow\Command;

use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs database and configuration updates for a single multisite.
 *
 * The shared per-site update: it runs drush deploy for one site, skipping a
 * site whose database is absent on the application or where Drupal is not
 * installed. Runnable on its own for a targeted update, and fanned out across
 * an application's sites by deploy:update.
 */
#[AsCommand(
  name: 'site:update',
  description: 'Run database and configuration updates for a single multisite.',
)]
class SiteUpdateCommand extends Command {

  use SiteNowCommandsTrait;

  /**
   * Exit code returned when a site is skipped (not updated, not failed).
   *
   * Distinct from SUCCESS (updated) and FAILURE (errored) so a caller can
   * report updated / skipped / failed separately from the exit code.
   *
   * The value 2 coincides with Symfony's Command::INVALID; that is harmless
   * here because site:update is always invoked with a fixed, valid argument and
   * never returns INVALID, so a 2 can only mean SKIPPED.
   */
  public const SKIPPED = 2;

  /**
   * Exit code returned when a site updated but its config does not match.
   *
   * The update finished, but config:status reports the active configuration
   * differs from the exported config. A caller can surface this as its own
   * "config does not match" tier without treating the site as failed.
   */
  public const CONFIG_MISMATCH = 3;

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates drush and the exported
   *   site UUID.
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
    $this->addArgument('site', InputArgument::REQUIRED, 'The site directory / canonical domain, e.g. brand.uiowa.edu.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $this->ansi = $output->isDecorated();
    $site = $input->getArgument('site');

    $app = getenv('AH_SITE_GROUP') ?: 'local';
    $is_acquia = (bool) getenv('AH_SITE_ENVIRONMENT');

    // Resolve the site directory the way Drupal does, via sites.php. For most
    // sites the directory is the host itself; an aliased host (notably an
    // application's own default site, addressed by a real domain but living
    // in the default directory) resolves to a different directory, which in
    // turn drives the settings-include name below.
    $dir = $this->siteDirectory($site);

    // Skip unless the site directory exists. Without this, an unresolved --uri
    // falls back to Drupal's default site, so a stale or mistyped site name
    // would silently run updates against default instead of being skipped.
    if (!is_dir("{$this->repoRoot}/docroot/sites/{$dir}")) {
      $io->writeln("Skipping {$site}: no site directory.");
      return self::SKIPPED;
    }

    // On Acquia, skip sites whose database is not present on this application.
    if ($is_acquia) {
      // Acquia names the settings include after the database.
      $db = $this->databaseName($site, $app);
      if (!is_file("/var/www/site-php/{$app}/{$db}-settings.inc")) {
        $io->writeln("Skipping {$site}: database not present on {$app}.");
        return self::SKIPPED;
      }
    }

    // Skip sites where Drupal is not installed.
    if (!$this->isInstalled($site)) {
      $io->writeln("Skipping {$site}: Drupal is not installed.");
      return self::SKIPPED;
    }

    $io->writeln("Updating {$site}...");

    // On Acquia the Twig cache must be invalidated explicitly for multisites;
    // it is handled automatically for the default site only.
    // @see https://support.acquia.com/hc/en-us/articles/360005167754
    $twig_script = '/var/www/site-scripts/invalidate-twig-cache.php';
    if ($is_acquia && is_file($twig_script)) {
      $this->drush(['php:script', $twig_script], uri: $site);
    }

    // Runs updatedb, config:import, cache:rebuild, and deploy:hook.
    if (!$this->runDeploy($io, $site)) {
      $io->error("Failed updating {$site}.");
      return Command::FAILURE;
    }

    // A site can finish drush deploy (exit zero) yet still have active config
    // that does not match the exported config, e.g. an import that could not
    // fully apply. config:status lists differing items on stdout and exits zero
    // whether or not any exist, so a non-zero exit means the check itself could
    // not run. Distinguish three outcomes: matches, does not match, and could
    // not verify; the latter two return CONFIG_MISMATCH so a caller can surface
    // them without treating the site as failed.
    $status = $this->drush(['config:status'], uri: $site);
    $config_state = Command::SUCCESS;
    if (!$status->isSuccessful()) {
      $config_state = self::CONFIG_MISMATCH;
      $io->warning("Could not verify config on {$site}: config:status failed. Needs developer attention.");
    }
    elseif (trim($status->getOutput()) !== '') {
      $config_state = self::CONFIG_MISMATCH;
      $io->warning("Config does not match on {$site}: active configuration differs from the exported config. Needs developer attention.");
    }

    $io->writeln("Finished updating {$site}.");
    return $config_state;
  }

  /**
   * Run drush deploy for a site, retrying once on failure.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The console style for progress and warnings.
   * @param string $site
   *   The site host / canonical domain.
   *
   * @return bool
   *   TRUE if either attempt succeeded.
   */
  protected function runDeploy(SymfonyStyle $io, string $site): bool {
    // Record maintenance mode before deploying, so a failure that leaves it on
    // is never mistaken for the owner's choice.
    $maint_before = $this->maintenanceMode($site);

    // First attempt, only if maintenance mode could be read. If it couldn't
    // (e.g. a bootstrap failure), a failed deploy couldn't be safely restored.
    if ($maint_before !== NULL && $this->deploy($site)) {
      return TRUE;
    }

    // Before retrying, report why and undo what the failure left behind.
    if ($maint_before === NULL) {
      $io->warning("Could not read {$site} maintenance mode; retrying once with -vvv.");
      $maint_before = $this->maintenanceMode($site);
      if ($maint_before === NULL) {
        $io->warning("Still could not read {$site} maintenance mode; drush deploy never ran.");
        return FALSE;
      }
    }
    else {
      $io->warning("drush deploy failed on {$site}; retrying once with -vvv.");
      $this->restoreMaintenanceMode($io, $site, $maint_before);
    }

    // Retry verbosely, so a second failure has full logs.
    $deployed = $this->deploy($site, verbose: TRUE);

    // Restore even on success: drush deploy puts back the maintenance mode it
    // found, which may be one the first failure left on.
    $this->restoreMaintenanceMode($io, $site, $maint_before);

    return $deployed;
  }

  /**
   * Run drush deploy once.
   *
   * @param string $site
   *   The site host / canonical domain.
   * @param bool $verbose
   *   Whether to run with -vvv.
   *
   * @return bool
   *   TRUE if drush deploy succeeded.
   */
  private function deploy(string $site, bool $verbose = FALSE): bool {
    // --yes answers the update and config-import confirmations explicitly
    // rather than relying on the non-interactive default.
    $args = ['deploy', '--yes'];
    if ($verbose) {
      $args[] = '-vvv';
    }
    return $this->drush($args, stream: TRUE, uri: $site)->isSuccessful();
  }

  /**
   * Turn maintenance mode back off if a failed update left it on.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The console style for reporting.
   * @param string $site
   *   The site host / canonical domain.
   * @param bool $before
   *   Maintenance mode before the update; only FALSE allows turning it off.
   *   TRUE (the owner's choice) leaves the site untouched.
   */
  private function restoreMaintenanceMode(SymfonyStyle $io, string $site, bool $before): void {
    // Nothing to do if maintenance mode isn't currently on.
    if ($this->maintenanceMode($site) === FALSE) {
      return;
    }

    // Maintenance mode was on prior to update. Nothing to do.
    if ($before) {
      return;
    }

    // Maintenance mode was off prior to update.
    // Turn back off and log a message.
    $set = $this->drush(['maint:set', '0'], uri: $site);
    if ($set->isSuccessful()) {
      $io->writeln("Took {$site} out of the maintenance mode left on by the failed update.");
    }
    else {
      $io->warning("Could not take {$site} out of maintenance mode after the failed update. Needs developer attention.");
    }
  }

  /**
   * Whether a site is in maintenance mode.
   *
   * @return bool|null
   *   The maintenance mode state, or NULL if it could not be read.
   */
  protected function maintenanceMode(string $site): ?bool {
    $result = $this->drush(['maint:get'], uri: $site);
    if (!$result->isSuccessful()) {
      return NULL;
    }
    return match (trim($result->getOutput())) {
      '1' => TRUE,
      '0' => FALSE,
      default => NULL,
    };
  }

  /**
   * Whether Drupal is installed for a site (the config table exists).
   */
  private function isInstalled(string $site): bool {
    $result = $this->drush(['sql:query', "SHOW TABLES LIKE 'config'"], uri: $site);
    return $result->isSuccessful() && trim($result->getOutput()) === 'config';
  }

}
