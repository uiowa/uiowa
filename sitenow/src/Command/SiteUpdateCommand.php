<?php

namespace SiteNow\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Runs database and configuration updates for a single multisite.
 *
 * The per-site half of the post-deploy update, invoked once per site by
 * deploy:update (in parallel) and runnable on its own for a targeted update.
 *
 * Drupal resolves a multisite from the --uri host (the site directory name is
 * the canonical host), so each site is targeted with --uri=<site>. On Acquia,
 * sites whose database is not present on the application are skipped, as are
 * sites where Drupal is not installed.
 */
#[AsCommand(
  name: 'site:update',
  description: 'Run database and config updates (drush deploy) for a single multisite.',
)]
class SiteUpdateCommand extends Command {

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
    $site = $input->getArgument('site');

    $app = getenv('AH_SITE_GROUP') ?: 'local';
    $is_acquia = (bool) getenv('AH_SITE_ENVIRONMENT');

    // Resolve the site directory the way Drupal does, via sites.php. For most
    // sites the directory is the host itself; an aliased host (notably the
    // default site, addressed as demo.sitenow.uiowa.edu but living in the
    // default directory) resolves to a different directory, which in turn
    // drives the settings-include name below.
    $dir = $this->siteDirectory($site);

    // Skip unless the site directory exists. Without this, an unresolved --uri
    // falls back to Drupal's default site, so a stale or mistyped site name
    // would silently run updates against default instead of being skipped.
    if (!is_dir("{$this->repoRoot}/docroot/sites/{$dir}")) {
      $io->writeln("Skipping {$site}: no site directory.");
      return Command::SUCCESS;
    }

    // On Acquia, skip sites whose database is not present on this application.
    if ($is_acquia) {
      // Acquia names the settings include after the site directory, except the
      // default site, which uses the application-named include and database.
      $db = $dir === 'default' ? $app : str_replace(['.', '-'], '_', $dir);
      if (!is_file("/var/www/site-php/{$app}/{$db}-settings.inc")) {
        $io->writeln("Skipping {$site}: database not present on {$app}.");
        return Command::SUCCESS;
      }
    }

    // Skip sites where Drupal is not installed.
    if (!$this->isInstalled($site)) {
      $io->writeln("Skipping {$site}: Drupal is not installed.");
      return Command::SUCCESS;
    }

    $io->writeln("Deploying updates to {$site}...");

    // On Acquia the Twig cache must be invalidated explicitly for multisites;
    // it is handled automatically for the default site only.
    // @see https://support.acquia.com/hc/en-us/articles/360005167754
    $twig_script = '/var/www/site-scripts/invalidate-twig-cache.php';
    if ($is_acquia && is_file($twig_script)) {
      $this->drush($site, ['php:script', $twig_script]);
    }

    // Runs updatedb, config:import, cache:rebuild, and deploy:hook. --yes
    // answers the update and config-import confirmations explicitly rather
    // than relying on the non-interactive default.
    $deploy = $this->drush($site, ['deploy', '--yes'], TRUE);
    if (!$deploy->isSuccessful()) {
      $io->error("Failed deploying updates to {$site}.");
      return Command::FAILURE;
    }

    $io->writeln("Finished deploying updates to {$site}.");
    return Command::SUCCESS;
  }

  /**
   * Resolve a host to its multisite directory via sites.php.
   *
   * Mirrors Drupal's own aliasing: sites.php maps alias hosts to a directory,
   * and a host with no entry uses a same-named directory. This is what lets the
   * default site be addressed by its real domain (demo.sitenow.uiowa.edu) while
   * resolving to the default directory.
   *
   * @param string $host
   *   The site host / canonical domain.
   *
   * @return string
   *   The multisite directory name.
   */
  private function siteDirectory(string $host): string {
    $sites = [];
    $sites_file = "{$this->repoRoot}/docroot/sites/sites.php";
    if (is_file($sites_file)) {
      // sites.php populates $sites with host => directory aliases.
      include $sites_file;
    }
    return $sites[$host] ?? $host;
  }

  /**
   * Whether Drupal is installed for a site (the config table exists).
   */
  private function isInstalled(string $site): bool {
    $result = $this->drush($site, ['sql:query', "SHOW TABLES LIKE 'config'"]);
    return $result->isSuccessful() && trim($result->getOutput()) === 'config';
  }

  /**
   * Run a drush command against a site and return the finished process.
   *
   * @param string $site
   *   The site to target via --uri.
   * @param string[] $args
   *   Drush command and arguments.
   * @param bool $stream
   *   TRUE to stream output live (used for the long-running deploy).
   *
   * @return \Symfony\Component\Process\Process
   *   The finished process.
   */
  private function drush(string $site, array $args, bool $stream = FALSE): Process {
    $process = new Process(
      ["{$this->repoRoot}/vendor/bin/drush", "--uri={$site}", ...$args],
      $this->repoRoot,
    );
    $process->setTimeout(NULL);
    if ($stream) {
      $process->run(fn($type, $buffer) => print $buffer);
    }
    else {
      $process->run();
    }
    return $process;
  }

}
