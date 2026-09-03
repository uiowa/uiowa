<?php

namespace SiteNow\Command;

use SiteNow\Process\FleetRunner;
use SiteNow\Report\CsvWriter;
use SiteNow\Report\FleetDomains;
use SiteNow\Traits\DescribesDrushFailures;
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
 *
 * One row per site in the manifest. Each site answers two questions, so the
 * fleet runs twice: once for the latest non-admin node revision, once for the
 * latest non-admin login. A site that fails a query reports N/A for that
 * column rather than dropping out of the report.
 */
#[AsCommand(
  name: 'report:inactive',
  description: 'Report inactive SiteNow sites (no recent login or revision).',
  aliases: ['inactive'],
)]
class ReportInactiveCommand extends Command {

  use SiteNowCommandsTrait;
  use ParsesListOptions;
  use DescribesDrushFailures;

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root.
   *   Locates sitenow/manifest.yml and the CSV export location.
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
      ->addOption('exclude', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated site domains to skip.', '')
      ->addOption('threshold', NULL, InputOption::VALUE_REQUIRED, 'Inactivity threshold (e.g. "1 year", "6 months").', '1 year')
      ->addOption('export', NULL, InputOption::VALUE_NONE, 'Export results to a CSV file at the repository root.')
      ->addOption('domains', NULL, InputOption::VALUE_NONE, 'Add a launch status column (Live / In Progress) by cross-checking Acquia Cloud for a registered domain. Requires Acquia credentials.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();

    $now = time();
    $target_apps = $this->parseList($input->getOption('apps'));
    $exclude = $this->parseList($input->getOption('exclude'));
    $export = (bool) $input->getOption('export');

    $threshold = trim($input->getOption('threshold'));
    $cutoff = strtotime("-{$threshold}", $now);
    if ($cutoff === FALSE) {
      $err->error("Could not parse threshold '{$threshold}'.");
      return Command::FAILURE;
    }

    if (!$this->requireSshAgent($io)) {
      return Command::FAILURE;
    }

    $runner = new FleetRunner($this->repoRoot);
    try {
      $selection = $runner->select($target_apps, $exclude);
    }
    catch (\RuntimeException $e) {
      $err->error($e->getMessage());
      return Command::FAILURE;
    }

    $site_count = array_sum(array_map('count', $selection));
    if ($site_count === 0) {
      $err->error('No sites matched the selection.');
      return Command::FAILURE;
    }

    $show_domains = (bool) $input->getOption('domains');
    $live_domains = [];
    if ($show_domains) {
      $client = $this->requireAcquiaClient($io);
      if ($client === NULL) {
        return Command::FAILURE;
      }

      $err->writeln('<comment>Checking registered domains on Acquia Cloud...</comment>');
      $applications = $this->getSortedApplications($client);
      $fleet = new FleetDomains($client);
      foreach ($fleet->iterate($applications, array_keys($selection), ['prod']) as $row) {
        $live_domains[$row['app']][$row['domain']] = TRUE;
      }
    }

    $headers = ['Application',
      'URL',
    ];
    if ($show_domains) {
      $headers[] = 'Launch Status';
    }
    $headers = array_merge($headers, [
      'Days Since Revision',
      'Days Since Login',
      "Login Inactive: {$threshold}",
      'Site Mail',
      'Webmasters',
    ]);

    $err->writeln("<comment>Checking last revision on {$site_count} sites...</comment>");
    $revisions = $runner->run($selection, [
      'sqlq',
      'SELECT MAX(revision_timestamp) FROM node_revision WHERE revision_uid != 1',
      '--no-interaction',
    ], 'prod', NULL, $this->progress($err, 'revision'));

    $err->writeln("<comment>Checking last login on {$site_count} sites...</comment>");
    $logins = $runner->run($selection, [
      'users:list',
      '--no-roles=administrator',
      '--format=json',
      '--no-interaction',
    ], 'prod', NULL, $this->progress($err, 'login'));
    $site_mails = $runner->run($selection, [
      'cget',
      'system.site',
      'mail',
      '--format=string',
    ], 'prod', NULL, $this->progress($err, 'mail'));

    $writer = $export ? new CsvWriter($this->repoRoot, 'SiteNow-Inactive-Report', $headers, [
      $target_apps ? implode('+', $target_apps) : 'all-apps',
      $threshold,
    ]) : NULL;
    $rows = [];

    // Walked in manifest order rather than completion order, so the report
    // reads the same whichever site the pool happens to finish first.
    foreach ($selection as $app_name => $domains) {
      foreach ($domains as $domain) {
        $last_revision = $this->parseLastRevision($revisions[$domain]['output'], $revisions[$domain]['exit']);
        if ($last_revision === FALSE) {
          $days_since_revision = 'N/A';
        }
        elseif ($last_revision === NULL) {
          $days_since_revision = 'Never';
        }
        else {
          $days_since_revision = ceil(($now - $last_revision) / 86400);
        }

        $users = $this->cleanUserList($logins[$domain]['output'], $logins[$domain]['exit']);

        // If we don't have an array of users,
        // just pass the value forward to last_login
        // for error handling.
        if (!is_array($users)) {
          $last_login = $users;
        }
        else {
          $last_login = $this->parseLastLogin($users);
        }
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

        $webmasters = $users ? $this->filterUsers($users) : [];
        $user_output = $webmasters ? implode(',', array_column($webmasters, 'mail')) : 'N/A';

        if ($site_mails[$domain]['exit'] !== 0 || empty($site_mails[$domain]['output'])) {
          $site_mail = FALSE;
        }
        else {
          $site_mail = $site_mails[$domain]['output'];
        }
        $site_mail_output = ($site_mail) ?: 'N/A';

        $row = [$app_name, $domain];
        if ($show_domains) {
          $row[] = $this->isLive($domain, $app_name, $live_domains) ? 'Live' : 'In Progress';
        }
        array_push($row, $days_since_revision, $days_since_login, $status, $site_mail_output, $user_output);
        if ($writer) {
          $writer->writeRow($row);
        }
        else {
          $rows[] = $row;
        }
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
   * Build the per-pass progress callback.
   *
   * The pool retries failures and reports progress per attempt, so a line here
   * describes one attempt, not the site's outcome: a site that fails its first
   * attempt and succeeds on retry still gets a real value in the report. The
   * query name is included because a site is asked two separate questions and
   * can fail either one.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $err
   *   The error output failures are reported on.
   * @param string $query
   *   The name of the query being run, e.g. 'login'.
   *
   * @return callable
   *   A ProcessPool progress callback.
   */
  protected function progress(OutputInterface $err, string $query): callable {
    return function (int $done, int $total, ?string $key, ?array $result) use ($err, $query) {
      if ($key === NULL || $result === NULL) {
        return;
      }
      if ($result['exit'] !== 0) {
        $err->writeln("<error>✖</error> [{$done}/{$total}] {$query} attempt failed: {$key} — " . $this->failureReason($result));
      }
    };
  }

  /**
   * Determine whether a site's manifest domain is registered on Acquia.
   *
   * A newly provisioned site only has internal domains,
   * and should be considered "in progress" and otherwise
   * not "live" until its manifest domain has been added.
   *
   * @param string $domain
   *   The site's manifest domain.
   * @param string $app
   *   The application the site belongs to.
   * @param array<string, array<string, bool>> $live_domains
   *   Registered domains per app, keyed as built from FleetDomains::iterate().
   *
   * @return bool
   *   TRUE if the domain is registered on the app's prod environment.
   */
  protected function isLive(string $domain, string $app, array $live_domains): bool {
    return isset($live_domains[$app][$domain]);
  }

  /**
   * Clean `users:list --format=json` output for easier processing.
   *
   * @param string $output
   *   The drush stdout.
   * @param int $exit_code
   *   The drush exit code.
   *
   * @return array|null|false
   *   The decoded user list keyed by uid, NULL when there is no user data, or
   *   FALSE on a non-zero exit or unparseable output.
   */
  protected function cleanUserList(string $output, int $exit_code): array|null|false {
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
    return $users;
  }

  /**
   * Parse `users:list --format=json` output into the latest login timestamp.
   *
   * @param array $users
   *   The decoded user list, as returned by cleanUserList().
   *
   * @return int|null
   *   Latest non-admin login timestamp, or NULL when there is no login data.
   */
  protected function parseLastLogin(array $users): int|null {
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
   * Filter `users:list --format=json` on the provided roles list.
   *
   * @param array $users
   *   The decoded user list, as returned by cleanUserList().
   * @param array $roles
   *   The roles on which to filter.
   *
   * @return array
   *   The users which have at least one of the given roles.
   */
  protected function filterUsers(array $users, array $roles = ['webmaster']): array {
    $user_list = [];
    foreach ($users as $user) {
      if (isset($user['uid']) && $user['uid'] == 1) {
        continue;
      }
      if (isset($user['status']) && $user['status'] !== 'active') {
        continue;
      }
      if (!isset($user['roles'])) {
        continue;
      }
      if (!empty(array_intersect($roles, $user['roles']))) {
        $user_list[] = $user;
      }
    }
    return $user_list;
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
