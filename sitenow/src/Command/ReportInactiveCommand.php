<?php

namespace SiteNow\Command;

use SiteNow\Report\CsvWriter;
use SiteNow\Report\DrushRunner;
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
 * Reports SiteNow sites with no recent content revision or user login.
 */
#[AsCommand(
  name: 'report:inactive',
  description: 'Report inactive SiteNow sites (no recent login or revision).',
  aliases: ['inactive'],
)]
class ReportInactiveCommand extends Command {

  use SiteNowCommandsTrait;
  use ParsesListOptions;

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
      ->addOption('apps', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated app names to filter by (e.g. uiowa,uiowa03).', '')
      ->addOption('threshold', NULL, InputOption::VALUE_REQUIRED, 'Inactivity threshold (e.g. "1 year", "6 months").', '1 year')
      ->addOption('export', NULL, InputOption::VALUE_NONE, 'Export results to a CSV file at the repository root.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();

    if (!$this->isDdev()) {
      $io->error('This command must be run inside the DDEV container. Use: ddev exec ./sn report:inactive');
      return Command::FAILURE;
    }
    if (!$this->hasSshAgent()) {
      $io->error("No SSH keys loaded. Please 'ddev auth ssh' before running this command.");
      return Command::FAILURE;
    }

    $now = time();
    $target_apps = $this->parseList($input->getOption('apps'));
    $export = (bool) $input->getOption('export');

    $threshold = trim($input->getOption('threshold'));
    $cutoff = strtotime("-{$threshold}", $now);
    if ($cutoff === FALSE) {
      $io->error("Could not parse threshold '{$threshold}'.");
      return Command::FAILURE;
    }

    $headers = ['Application', 'URL', 'Days Since Revision', 'Days Since Login', "Login Inactive: {$threshold}"];

    $client = $this->requireAcquiaClient($io);
    if ($client === NULL) {
      return Command::FAILURE;
    }

    $err->writeln('<comment>Fetching domains from Acquia Cloud API...</comment>');

    $applications = $this->getSortedApplications($client);
    $fleet = new FleetDomains($client);
    $runner = new DrushRunner();

    $writer = $export ? new CsvWriter($this->repoRoot, 'SiteNow-Inactive-Report', $headers) : NULL;
    $rows = [];

    foreach ($fleet->iterate($applications, $target_apps, ['prod'], function (string $app_name) use ($err) {
      $err->writeln("Processing {$app_name}...");
    }) as $site) {
      $domain = $site['domain'];
      $err->writeln("  Checking {$domain}...");

      $last_revision = $this->getLastContentRevision($runner, $domain);
      if ($last_revision === FALSE) {
        $days_since_revision = 'N/A';
      }
      elseif ($last_revision === NULL) {
        $days_since_revision = 'Never';
      }
      else {
        $days_since_revision = ceil(($now - $last_revision) / 86400);
      }

      $last_login = $this->getLastUserLogin($runner, $domain);
      if ($last_login === FALSE) {
        $days_since_login = 'N/A';
        $status = 'Error';
      }
      elseif ($last_login === NULL) {
        $days_since_login = 'Never';
        $status = 'Inactive';
      }
      else {
        $days_since_login = ceil(($now - $last_login) / 86400);
        $status = ($last_login < $cutoff) ? 'Inactive' : 'Active';
      }

      $row = [$site['app'], $domain, $days_since_revision, $days_since_login, $status];
      if ($writer) {
        $writer->writeRow($row);
      }
      else {
        $rows[] = $row;
      }
    }

    if ($writer) {
      $io->success("Results exported to {$writer->getPath()}");
    }
    else {
      $io->table($headers, $rows);
    }

    return Command::SUCCESS;
  }

  /**
   * Get the last non-admin user login timestamp via drush alias (prod).
   *
   * @param \SiteNow\Report\DrushRunner $runner
   *   The drush runner.
   * @param string $multisite
   *   The multisite domain.
   *
   * @return int|null|false
   *   Unix timestamp if found, NULL if no login data, FALSE on a query error.
   */
  protected function getLastUserLogin(DrushRunner $runner, string $multisite): int|null|false {
    $alias = $this->getDrushAlias($multisite) . '.prod';
    $result = $runner->run($alias, ['users:list', '--no-roles=administrator', '--format=json', '--no-interaction']);

    return $this->parseLastLogin($result['output'], $result['exit']);
  }

  /**
   * Parse `users:list --format=json` output into the latest login timestamp.
   *
   * @param string $output
   *   The drush stdout.
   * @param int $exit_code
   *   The drush exit code.
   *
   * @return int|null|false
   *   Latest non-admin login timestamp, NULL when there is no login data, or
   *   FALSE on a non-zero exit or unparseable output.
   */
  protected function parseLastLogin(string $output, int $exit_code): int|null|false {
    if ($exit_code !== 0 || trim($output) === '') {
      return FALSE;
    }

    // Strip any leading connection chatter before the JSON object.
    if (($pos = strpos($output, '{')) !== FALSE) {
      $output = substr($output, $pos);
    }

    $users = json_decode($output, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return FALSE;
    }
    if (!is_array($users) || empty($users)) {
      return NULL;
    }

    $latest_login = NULL;
    $floor = strtotime('2000-01-01');

    foreach ($users as $user) {
      if (isset($user['uid']) && $user['uid'] == 1) {
        continue;
      }
      if (empty($user['login'])) {
        continue;
      }

      $login_time = strtotime($user['login']);
      // Skip the UNIX-epoch default (Dec 31, 1969) and other pre-2000 noise.
      if ($login_time && $login_time > $floor && ($latest_login === NULL || $login_time > $latest_login)) {
        $latest_login = $login_time;
      }
    }

    return $latest_login;
  }

  /**
   * Get the timestamp of the last non-admin node revision via drush (prod).
   *
   * @param \SiteNow\Report\DrushRunner $runner
   *   The drush runner.
   * @param string $multisite
   *   The multisite domain.
   *
   * @return int|null|false
   *   Unix timestamp if found, NULL if no revisions, FALSE on a query error.
   */
  protected function getLastContentRevision(DrushRunner $runner, string $multisite): int|null|false {
    $alias = $this->getDrushAlias($multisite) . '.prod';
    $result = $runner->run($alias, [
      'sqlq',
      'SELECT MAX(revision_timestamp) FROM node_revision WHERE revision_uid != 1',
      '--no-interaction',
    ]);

    return $this->parseLastRevision($result['output'], $result['exit']);
  }

  /**
   * Parse the `sqlq` MAX(revision_timestamp) output into a timestamp.
   *
   * @param string $output
   *   The drush stdout.
   * @param int $exit_code
   *   The drush exit code.
   *
   * @return int|null|false
   *   The revision timestamp, NULL when there are no revisions (0/empty), or
   *   FALSE on a non-zero exit or no numeric output.
   */
  protected function parseLastRevision(string $output, int $exit_code): int|null|false {
    if ($exit_code !== 0) {
      return FALSE;
    }

    foreach (preg_split('/\R/', $output) as $line) {
      $line = trim($line);
      if (is_numeric($line)) {
        $timestamp = (int) $line;
        return $timestamp > 0 ? $timestamp : NULL;
      }
    }

    return FALSE;
  }

}
