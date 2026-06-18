<?php

namespace SiteNow\Command;

use SiteNow\Report\CsvWriter;
use SiteNow\Report\FleetDomains;
use SiteNow\Traits\ParsesListOptions;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists customer-facing domains across the Acquia application fleet.
 */
#[AsCommand(
  name: 'report:domains',
  description: 'List domains on prod (default) or specified environments.',
  aliases: ['domains'],
)]
class ReportDomainsCommand extends Command {

  use SiteNowCommandsTrait;
  use ParsesListOptions;

  const HEADERS = ['Application', 'Environment', 'URL'];

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Used for the CSV export location.
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
      ->addOption('export', NULL, InputOption::VALUE_NONE, 'Export results to a CSV file at the repository root.')
      ->addOption('env', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated environments to include (e.g. dev,test). Defaults to prod.', '')
      ->addOption('apps', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated app names to include (e.g. uiowa02,uiowa03). Defaults to all.', '');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();

    if (!$this->requireDdev($io, $this->getName())) {
      return Command::FAILURE;
    }

    $target_envs = $this->parseList($input->getOption('env')) ?: ['prod'];
    $target_apps = $this->parseList($input->getOption('apps'));
    $export = (bool) $input->getOption('export');

    $client = $this->requireAcquiaClient($io);
    if ($client === NULL) {
      return Command::FAILURE;
    }

    $err->writeln('<comment>Checking environments across Acquia Cloud applications...</comment>');

    $applications = $this->getSortedApplications($client);
    $fleet = new FleetDomains($client);

    $writer = $export ? new CsvWriter($this->repoRoot, 'SiteNow-Domains-Report', self::HEADERS) : NULL;
    $rows = [];

    foreach ($fleet->iterate($applications, $target_apps, $target_envs, function (string $app_name) use ($err) {
      $err->writeln("  {$app_name}");
    }) as $row) {
      $line = [$row['app'], $row['env'], $row['domain']];
      if ($writer) {
        $writer->writeRow($line);
      }
      else {
        $rows[] = $line;
      }
    }

    if ($writer) {
      $io->success("Results exported to {$writer->getPath()}");
    }
    else {
      $io->table(self::HEADERS, $rows);
    }

    return Command::SUCCESS;
  }

}
