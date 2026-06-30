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
 * Ports BLT's uiowa:site:update / updateSite.
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

    // Skip unless the site directory exists. Without this, an unresolved --uri
    // falls back to Drupal's default site, so a stale or mistyped site name
    // would silently run updates against default instead of being skipped.
    if (!is_dir("{$this->repoRoot}/docroot/sites/{$site}")) {
      $io->writeln("Skipping {$site}: no site directory.");
      return Command::SUCCESS;
    }

    // On Acquia, skip sites whose database is not present on this application.
    if ($is_acquia) {
      $db = str_replace(['.', '-'], '_', $site);
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

    // The site UUID that config:import requires is established once at install
    // (drush site:install --existing-config adopts the exported UUID), so the
    // deploy does not reconcile it. A genuine mismatch surfaces as a config
    // import failure, which is the right signal rather than one to mask.
    //
    // Runs updatedb, config:import, cache:rebuild, and deploy:hook.
    $deploy = $this->drush($site, ['deploy'], TRUE);
    if (!$deploy->isSuccessful()) {
      $io->error("Failed deploying updates to {$site}.");
      return Command::FAILURE;
    }

    $io->writeln("Finished deploying updates to {$site}.");
    return Command::SUCCESS;
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
