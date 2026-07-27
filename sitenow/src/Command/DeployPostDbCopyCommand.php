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

/**
 * Reconciles the copied site after an Acquia database copy.
 *
 * The BLT-independent replacement for the post-db-copy cloud hook. Acquia copies
 * one site's database between environments and fires the hook with the copied
 * database's name; the reconcile must bring that one site's database in line
 * with the target environment's code (updatedb, config import, deploy hooks) so
 * the copy is usable. This resolves the database name back to its site and
 * delegates the reconcile to site:update.
 *
 * Runs on the Acquia server from the hook, not in DDEV: it operates on the local
 * site there, so it needs neither the container nor a forwarded SSH agent.
 */
#[AsCommand(
  name: 'deploy:post-db-copy',
  description: 'Reconcile a single site after its database was copied between Acquia environments.',
)]
class DeployPostDbCopyCommand extends Command {

  use SiteNowCommandsTrait;

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates the manifest and the sn
   *   binary used for the reconcile.
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
      ->addArgument('db-name', InputArgument::REQUIRED, 'The copied database name, as passed by the Acquia post-db-copy hook (e.g. brand_uiowa_edu).')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Resolve and print the site the database belongs to without reconciling it.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();
    $this->ansi = $output->isDecorated();

    $db_name = $input->getArgument('db-name');
    $app = getenv('AH_SITE_GROUP') ?: '';
    if ($app === '') {
      $err->error('AH_SITE_GROUP is not set. The Acquia post-db-copy hook sets it automatically; to run this command by hand (e.g. testing) set it to the target application first — e.g. AH_SITE_GROUP=uiowa09 ./sn deploy:post-db-copy <db-name>.');
      return Command::FAILURE;
    }

    // Resolving anything but the application's own database needs the manifest,
    // and a missing one must not read as "no site matches" — that would let the
    // hook pass without reconciling the copy.
    if ($db_name !== $app && !$this->requireManifest($io)) {
      return Command::FAILURE;
    }

    $site = $this->resolveSite($db_name, $app);
    if ($site === NULL) {
      // A copy of a database this application does not own should not fail the
      // hook; there is simply nothing here to reconcile.
      $io->warning("No site in application '{$app}' matches database '{$db_name}'. Nothing to reconcile.");
      return Command::SUCCESS;
    }

    if ($input->getOption('dry-run')) {
      $io->writeln("Database '{$db_name}' resolves to {$site}.");
      return Command::SUCCESS;
    }

    $io->writeln("Database '{$db_name}' belongs to {$site}; reconciling...");
    if (!$this->updateSite($site)) {
      $err->error("Reconcile failed for {$site}.");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * Resolve a copied database name back to its site domain.
   *
   * Inverts databaseName() over this application's sites (AH_SITE_GROUP, via
   * the manifest) — a copy targets one of them. Sharing that derivation is what
   * keeps this and site:update agreeing on which database belongs to which
   * site, including for a host that sites.php aliases to another directory.
   *
   * @param string $db_name
   *   The copied database name from the hook.
   * @param string $app
   *   The application (AH_SITE_GROUP).
   *
   * @return string|null
   *   The matching site domain (or 'default'), or NULL if none matches.
   */
  private function resolveSite(string $db_name, string $app): ?string {
    // The application-named database is the default site's, whether or not the
    // manifest lists a domain for it — most applications do not.
    if ($db_name === $app) {
      return 'default';
    }

    foreach ($this->manifestSites($app) as $domain) {
      if ($this->databaseName($domain, $app) === $db_name) {
        return $domain;
      }
    }

    return NULL;
  }

}
