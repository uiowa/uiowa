<?php

namespace SiteNow\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

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

    $db_name = $input->getArgument('db-name');
    $app = getenv('AH_SITE_GROUP') ?: '';
    if ($app === '') {
      $err->error('AH_SITE_GROUP is not set. This command runs from the Acquia post-db-copy hook.');
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
    $update = new Process(
      ["{$this->repoRoot}/sn", 'site:update', $site, $output->isDecorated() ? '--ansi' : '--no-ansi'],
      $this->repoRoot,
    );
    $update->setTimeout(NULL);
    $update->run(fn ($type, $buffer) => print $buffer);

    // A skip or config-mismatch exit from site:update is not a hook failure;
    // only a genuine update error should surface as one.
    $tolerated = [Command::SUCCESS, SiteUpdateCommand::SKIPPED, SiteUpdateCommand::CONFIG_MISMATCH];
    if (!in_array($update->getExitCode(), $tolerated, TRUE)) {
      $err->error("Reconcile failed for {$site}.");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * Resolve a copied database name back to its site domain.
   *
   * Acquia names each multisite's database after its directory with dots and
   * hyphens replaced by underscores; the default site's database is named after
   * the application. This mirrors the derivation site:update uses, so the two
   * agree on which database belongs to which site. Only this application's sites
   * (AH_SITE_GROUP, via the manifest) are considered — a copy targets one of
   * them.
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
    // The default site's database is named after the application.
    if ($db_name === $app) {
      return 'default';
    }

    $manifest = Yaml::parseFile("{$this->repoRoot}/blt/manifest.yml") ?: [];
    foreach ($manifest[$app] ?? [] as $domain) {
      if (str_replace(['.', '-'], '_', $domain) === $db_name) {
        return $domain;
      }
    }

    return NULL;
  }

}
