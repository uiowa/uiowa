<?php

namespace SiteNow\Command;

use SiteNow\Install\InstallStatus;
use SiteNow\Traits\ClassifiesInstalls;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Installs Drupal for one multisite, or heals an incomplete install.
 *
 * Replaces `blt drupal:install --site=X` together with the repository's
 * post-command hook on it, so the install and the uiowa steps that have to
 * follow it — site name, requester, config splits — are one command rather than
 * a command plus a hook that only fires under BLT.
 *
 * Safe to run repeatedly. What it does depends on what it finds: a site with no
 * Drupal gets installed, an install that never finished gets reinstalled (only
 * when it holds nothing), and a complete install gets its post-install steps
 * reapplied. Each of those steps is individually idempotent, which is what
 * makes a second run a repair rather than a risk.
 *
 * Runnable on its own for one site, and fanned out across an application by
 * multisite:install.
 */
#[AsCommand(
  name: 'site:install',
  description: 'Install Drupal for a single multisite, or heal an incomplete install.',
)]
class SiteInstallCommand extends Command {

  use ClassifiesInstalls;
  use SiteNowCommandsTrait;

  /**
   * Exit code returned when a site is skipped (not installed, not failed).
   *
   * Matches SiteUpdateCommand::SKIPPED so a caller reads the same code from
   * either command.
   */
  public const SKIPPED = 2;

  /**
   * Exit code returned when the site installed but its config does not match.
   *
   * The install finished, but config:status reports the active configuration
   * differs from the exported config. Matches SiteUpdateCommand.
   */
  public const CONFIG_MISMATCH = 3;

  /**
   * Exit code returned when a partial install was refused.
   *
   * The site's install never finished, but it holds content a reinstall would
   * destroy. Nothing was changed; a human decides.
   */
  public const BLOCKED = 4;

  /**
   * The install profile, when blt/blt.yml cannot be read.
   */
  const PROFILE = 'sitenow';

  /**
   * Extra form state passed to the installer.
   *
   * Suppresses the update status module the install form would otherwise
   * enable. Carried over verbatim from BLT's setup.install-args.
   */
  const INSTALL_ARGS = 'install_configure_form.enable_update_status_module=NULL';

  /**
   * The site and account mail address the installer records.
   *
   * BLT's drupal.account.mail default, which this repository never overrode.
   * Neither address is used: user 1 is not a login anyone holds.
   */
  const MAIL = 'no-reply@example.com';

  /**
   * The install locale.
   */
  const LOCALE = 'en';

  /**
   * The exported configuration directory, relative to the repository root.
   */
  const CONFIG_DIR = 'config/default';

  /**
   * The role a site's requester is granted.
   */
  const REQUESTER_ROLE = 'webmaster';

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates drush, the site's blt.yml,
   *   and the exported configuration.
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
      ->addArgument('site', InputArgument::REQUIRED, 'The site host / canonical domain, e.g. brand.uiowa.edu.')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Report what the site needs without changing anything.')
      ->addOption('force', NULL, InputOption::VALUE_NONE, 'Reinstall an incomplete install even though it holds content. Destroys that content.')
      ->setHelp(<<<'HELP'
Installs Drupal for one multisite and applies the post-install steps that have
to follow it: the site name from uiowa.site-name, the requester as a webmaster,
and any config splits from uiowa.config.split.

Run it again on a site that is already installed and it reapplies just those
post-install steps, reporting anything it had to correct. Run it on a site whose
install died partway and it reinstalls — unless the site holds content, which it
refuses to destroy without --force.

Needs a database connection, so off Acquia it runs inside the container:
  ddev exec ./sn site:install brand.uiowa.edu

Examples:
  # What does this site need?
  ddev exec ./sn site:install brand.uiowa.edu --dry-run

  # Install, or heal, one site.
  ./sn site:install brand.uiowa.edu
HELP);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();
    $this->ansi = $output->isDecorated();

    $site = $input->getArgument('site');
    $app = getenv('AH_SITE_GROUP') ?: 'local';
    $is_acquia = (bool) getenv('AH_SITE_ENVIRONMENT');

    // Every step here needs a database connection, and off Acquia the database
    // host only resolves inside the container.
    if (!$is_acquia && !$this->requireDdev($io, "site:install {$site}")) {
      return Command::FAILURE;
    }

    $state = $this->classifyInstall($site, $app, $is_acquia);
    $io->writeln("{$site}: {$state->describe()}");

    if ($input->getOption('dry-run')) {
      return Command::SUCCESS;
    }

    if ($state->status === InstallStatus::Unavailable) {
      return self::SKIPPED;
    }

    // Already installed: the install itself is done, so only the post-install
    // steps are left to reapply.
    if ($state->status === InstallStatus::Installed) {
      return $this->reconcile($io, $site);
    }

    if ($state->status === InstallStatus::Partial && $state->hasContent() && !$input->getOption('force')) {
      $err->error($state->contentUnknown
        ? "Refusing to reinstall {$site}: its content could not be checked, so a reinstall cannot be shown to be safe. Investigate the database, then re-run with --force to reinstall regardless."
        : "Refusing to reinstall {$site}: its unfinished install holds {$state->contentSummary()}. Inspect the site, then re-run with --force to reinstall and lose that content.");
      return self::BLOCKED;
    }

    if (!$this->install($io, $site)) {
      return Command::FAILURE;
    }

    return $this->reconcile($io, $site);
  }

  /**
   * Install Drupal for the site and import the exported configuration.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $site
   *   The site host / canonical domain.
   *
   * @return bool
   *   TRUE when the install and the config import both succeeded.
   */
  private function install(SymfonyStyle $io, string $site): bool {
    $dir = $this->siteDirectory($site);
    $config = $this->siteConfig($dir);

    $io->writeln("Installing {$site}...");

    // Clear caches first: a stale container left behind by an earlier partial
    // install can break the installer. A site with nothing to clear fails here,
    // which is expected and ignored.
    $this->drush(['cache:rebuild'], uri: $site);

    $args = [
      'site:install',
      $this->profile(),
      self::INSTALL_ARGS,
      "--sites-subdir={$dir}",
      // Overwritten by the exported system.site when installing from config,
      // and set to its final value by the post-install steps either way.
      '--site-name=' . ($config['project']['human_name'] ?? $site),
      '--site-mail=' . self::MAIL,
      // Random, so no site ships with a guessable user 1 name.
      '--account-name=' . $this->accountName(),
      '--account-mail=' . self::MAIL,
      '--locale=' . self::LOCALE,
      '--yes',
    ];

    // Install from the exported configuration when there is any to install
    // from; that is what brings a new site up already configured.
    if (is_file($this->configDir() . '/core.extension.yml')) {
      $args[] = '--existing-config';
    }

    if (!$this->drush($args, stream: TRUE, uri: $site)->isSuccessful()) {
      $io->error("Failed installing {$site}.");
      return FALSE;
    }

    if (!$this->importConfig($io, $site)) {
      return FALSE;
    }

    $this->invalidateTwigCache($site);
    $this->setPermissions($io, $dir);

    return TRUE;
  }

  /**
   * Invalidate the Twig cache, which Acquia does not do for multisites.
   *
   * Matters on a reinstall rather than a first install: the site already had a
   * Twig cache, and Acquia only invalidates it automatically for the default
   * site. Mirrors what site:update does for the same reason.
   *
   * @param string $site
   *   The site host / canonical domain.
   *
   * @see https://support.acquia.com/hc/en-us/articles/360005167754
   */
  private function invalidateTwigCache(string $site): void {
    $script = '/var/www/site-scripts/invalidate-twig-cache.php';

    if (getenv('AH_SITE_ENVIRONMENT') && is_file($script)) {
      $this->drush(['php:script', $script], uri: $site);
    }
  }

  /**
   * Import the exported configuration, splits included.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $site
   *   The site host / canonical domain.
   *
   * @return bool
   *   TRUE when the import succeeded.
   */
  private function importConfig(SymfonyStyle $io, string $site): bool {
    // Enabling config_split decides how config is imported: with it, a split
    // defined by one import pass needs a second pass to be applied; without it,
    // there is only core configuration to import.
    $split = $this->drush(['pm:enable', 'config_split', '--yes'], uri: $site)->isSuccessful();
    if (!$split) {
      $io->warning("config_split could not be enabled on {$site}; importing core configuration only.");
    }

    // A site whose UUID differs from the exported one cannot import that
    // configuration at all.
    // @see https://www.drupal.org/project/drupal/issues/1613424
    $uuid = $this->exportedSiteUuid();
    if ($uuid !== NULL) {
      $this->drush(['config:set', 'system.site', 'uuid', $uuid, '--yes'], uri: $site);
    }

    for ($pass = 0; $pass < ($split ? 2 : 1); $pass++) {
      if (!$this->drush(['config:import', '--yes'], stream: TRUE, uri: $site)->isSuccessful()) {
        $io->error("Failed importing configuration for {$site}.");
        return FALSE;
      }
    }

    $this->drush(['cache:rebuild'], uri: $site);

    return TRUE;
  }

  /**
   * Apply the post-install steps and report anything they had to correct.
   *
   * Every step is idempotent, so this is both the tail of an install and the
   * repair pass for a site that is already installed.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $site
   *   The site host / canonical domain.
   *
   * @return int
   *   SUCCESS, or CONFIG_MISMATCH when the site's active config does not match
   *   the exported config.
   */
  private function reconcile(SymfonyStyle $io, string $site): int {
    $config = $this->siteConfig($this->siteDirectory($site));

    $this->reconcileSiteName($io, $site, $config['uiowa']['site-name'] ?? $site);

    if (!empty($config['uiowa']['requester'])) {
      $this->reconcileRequester($io, $site, $config['uiowa']['requester']);
    }

    if (!empty($config['uiowa']['config']['split'])) {
      $split = $config['uiowa']['config']['split'];
      $this->reconcileSplits($io, $site, is_array($split) ? $split : [$split]);
    }

    return $this->checkConfigParity($io, $site);
  }

  /**
   * Ensure the site name matches what the site's blt.yml asks for.
   *
   * Installing from existing configuration overwrites the name the installer
   * was given with the exported one, so it has to be set afterwards.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $site
   *   The site host / canonical domain.
   * @param string $expected
   *   The name the site should have.
   */
  private function reconcileSiteName(SymfonyStyle $io, string $site, string $expected): void {
    $current = $this->currentSiteName($site);
    if ($current === $expected) {
      return;
    }

    if ($current !== NULL) {
      $io->writeln("Correcting site name on {$site}: '{$current}' -> '{$expected}'.");
    }

    if (!$this->drush(['config:set', 'system.site', 'name', $expected, '--yes'], uri: $site)->isSuccessful()) {
      $io->warning("Could not set the site name on {$site}.");
    }
  }

  /**
   * Read the site's current name, if it can be read.
   *
   * @param string $site
   *   The site host / canonical domain.
   *
   * @return string|null
   *   The name, or NULL when it could not be read — in which case the caller
   *   sets it unconditionally rather than skipping the step.
   */
  private function currentSiteName(string $site): ?string {
    $result = $this->drush(['config:get', 'system.site', 'name', '--format=json'], uri: $site);
    if (!$result->isSuccessful()) {
      return NULL;
    }

    $decoded = json_decode(trim($result->getOutput()), TRUE);

    return is_array($decoded) && $decoded !== [] ? (string) reset($decoded) : NULL;
  }

  /**
   * Ensure the requester exists and holds the webmaster role.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $site
   *   The site host / canonical domain.
   * @param string $requester
   *   The requester's username.
   */
  private function reconcileRequester(SymfonyStyle $io, string $site, string $requester): void {
    // user:information exits non-zero when it finds no such user, and
    // user:create exits non-zero when the user already exists, so the existence
    // check is what keeps this idempotent.
    if (!$this->drush(['user:information', $requester], uri: $site)->isSuccessful()) {
      $io->writeln("Creating user {$requester} on {$site}.");

      if (!$this->drush(['user:create', $requester], uri: $site)->isSuccessful()) {
        $io->warning("Could not create user {$requester} on {$site}.");
        return;
      }
    }

    // Granting a role the user already holds is a no-op.
    if (!$this->drush(['user:role:add', self::REQUESTER_ROLE, $requester], uri: $site)->isSuccessful()) {
      $io->warning("Could not grant the " . self::REQUESTER_ROLE . " role to {$requester} on {$site}.");
    }
  }

  /**
   * Ensure the site's config splits are active and imported.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $site
   *   The site host / canonical domain.
   * @param string[] $splits
   *   The config_split ids to activate.
   */
  private function reconcileSplits(SymfonyStyle $io, string $site, array $splits): void {
    foreach ($splits as $split) {
      // '1' rather than 'true': the status key is boolean in the config schema,
      // which casts it on save. Carried over from the BLT hook.
      $set = $this->drush([
        'config:set',
        "config_split.config_split.{$split}",
        'status',
        '1',
        '--yes',
      ], uri: $site);

      if (!$set->isSuccessful()) {
        $io->warning("Could not activate config split '{$split}' on {$site}.");
      }
    }

    $this->drush(['cache:rebuild'], uri: $site);

    if (!$this->drush(['config:import', '--yes'], stream: TRUE, uri: $site)->isSuccessful()) {
      $io->warning("Could not import split configuration on {$site}.");
    }
  }

  /**
   * Report whether the site's active config matches the exported config.
   *
   * A site can install cleanly and still end up with active configuration that
   * differs from what was exported. config:status lists differing items on
   * stdout and exits zero whether or not any exist, so a non-zero exit means
   * the check itself could not run. Both that and a real difference are
   * reported without treating the install as failed — the site is up, and
   * someone needs to look at it.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $site
   *   The site host / canonical domain.
   *
   * @return int
   *   SUCCESS or CONFIG_MISMATCH.
   */
  private function checkConfigParity(SymfonyStyle $io, string $site): int {
    $status = $this->drush(['config:status'], uri: $site);

    if (!$status->isSuccessful()) {
      $io->warning("Could not verify config on {$site}: config:status failed. Needs developer attention.");
      return self::CONFIG_MISMATCH;
    }

    if (trim($status->getOutput()) !== '') {
      $io->warning("Config does not match on {$site}: active configuration differs from the exported config. Needs developer attention.");
      return self::CONFIG_MISMATCH;
    }

    $io->writeln("Finished {$site}.");

    return Command::SUCCESS;
  }

  /**
   * Set the permissions Drupal expects on the site directory.
   *
   * Directories to 755 and files to 644 at the top level of the site
   * directory, leaving the files directory alone.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param string $dir
   *   The multisite directory name.
   */
  private function setPermissions(SymfonyStyle $io, string $dir): void {
    $path = "{$this->repoRoot}/docroot/sites/{$dir}";
    $failed = 0;

    foreach (scandir($path) ?: [] as $entry) {
      if (in_array($entry, ['.', '..', 'files'], TRUE)) {
        continue;
      }

      $target = "{$path}/{$entry}";
      if (!@chmod($target, is_dir($target) ? 0755 : 0644)) {
        $failed++;
      }
    }

    // Acquia Cloud denies chmod on the deployed code, where the permissions are
    // already correct. Not a failure there, and not worth a warning.
    if ($failed > 0) {
      $io->writeln("<comment>Note:</comment> could not set permissions on {$failed} item(s) in sites/{$dir}; expected on Acquia Cloud.");
    }
  }

  /**
   * Read the site's own blt.yml.
   *
   * The per-site file is the source of the values the install and post-install
   * steps need — human_name, uiowa.site-name, uiowa.requester,
   * uiowa.config.split — and holds them as literals, so it is read directly
   * rather than through BLT's layered configuration.
   *
   * @param string $dir
   *   The multisite directory name.
   *
   * @return array
   *   The parsed configuration, empty when the site has no blt.yml.
   */
  protected function siteConfig(string $dir): array {
    $path = "{$this->repoRoot}/docroot/sites/{$dir}/blt.yml";

    return is_file($path) ? (Yaml::parseFile($path) ?: []) : [];
  }

  /**
   * The install profile to install.
   *
   * @return string
   *   The profile name from blt/blt.yml, or the built-in default.
   */
  protected function profile(): string {
    $path = "{$this->repoRoot}/blt/blt.yml";
    $config = is_file($path) ? (Yaml::parseFile($path) ?: []) : [];

    return $config['project']['profile']['name'] ?? self::PROFILE;
  }

  /**
   * The absolute path to the exported configuration directory.
   *
   * @return string
   *   The directory path, present or not.
   */
  protected function configDir(): string {
    return "{$this->repoRoot}/" . self::CONFIG_DIR;
  }

  /**
   * The site UUID recorded in the exported configuration.
   *
   * @return string|null
   *   The UUID, or NULL when there is no exported system.site.
   */
  protected function exportedSiteUuid(): ?string {
    $path = $this->configDir() . '/system.site.yml';
    if (!is_file($path)) {
      return NULL;
    }

    return (Yaml::parseFile($path) ?: [])['uuid'] ?? NULL;
  }

  /**
   * Generate a random username for user 1.
   *
   * User 1 is not an account anyone signs in as; a random name keeps it from
   * being a known target on every site.
   *
   * @return string
   *   A ten-character username.
   */
  protected function accountName(): string {
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $name = '';

    for ($i = 0; $i < 10; $i++) {
      $name .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $name;
  }

}
