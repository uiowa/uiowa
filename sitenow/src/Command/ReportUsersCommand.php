<?php

namespace SiteNow\Command;

use SiteNow\Process\FleetRunner;
use SiteNow\Report\CsvWriter;
use SiteNow\Traits\ParsesListOptions;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reports SiteNow users and the sites they have logged into.
 *
 * One row per (user, site): a user who holds an editorial role on a site and
 * has logged into it within the threshold appears once for that site. Users
 * on multiple sites appear once per site. The role filter, the login-recency
 * filter, and the never-logged-in exclusion all run server-side in
 * `drush users:list`; this command collates the per-site JSON into a single
 * report.
 */
#[AsCommand(
  name: 'report:users',
  description: '(ddev required) Report SiteNow users and the sites they have logged into.',
  aliases: ['users'],
)]
class ReportUsersCommand extends Command {

  use SiteNowCommandsTrait;
  use ParsesListOptions;

  /**
   * Editorial roles that qualify a user for the report.
   *
   * Every site carries these base roles (config/default/). A user is included
   * for a site when they hold at least one of them there. Listed in ascending
   * privilege order so the roles column reads low-to-high.
   */
  const ROLES = ['viewer', 'editor', 'publisher', 'webmaster'];

  const HEADERS = ['Email', 'URL', 'Roles', 'Last Login'];

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates blt/manifest.yml and the
   *   CSV export location.
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
      ->addOption('apps', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated app names to include (e.g. uiowa02,uiowa03). Defaults to all.', '')
      ->addOption('exclude', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated site domains to skip.', '')
      ->addOption('exclude-users', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated email addresses to omit from the report.', '')
      ->addOption('threshold', NULL, InputOption::VALUE_REQUIRED, 'Login-recency window (e.g. "1 year", "6 months").', '1 year')
      ->addOption('export', NULL, InputOption::VALUE_NONE, 'Export results to a CSV file at the repository root.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();
    $start = microtime(TRUE);

    $target_apps = $this->parseList($input->getOption('apps'));
    $exclude = $this->parseList($input->getOption('exclude'));
    $exclude_users = array_map('strtolower', $this->parseList($input->getOption('exclude-users')));
    $threshold = trim($input->getOption('threshold'));
    $export = (bool) $input->getOption('export');

    if (strtotime("-{$threshold}") === FALSE) {
      $err->error("Could not parse threshold '{$threshold}'.");
      return Command::FAILURE;
    }

    if (!$this->requireDdev($io, $this->getName())) {
      return Command::FAILURE;
    }
    if (!$this->requireSshAgent($io)) {
      return Command::FAILURE;
    }

    $runner = new FleetRunner("{$this->repoRoot}/blt/manifest.yml", "{$this->repoRoot}/drush/drush.yml");
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

    $drush_args = [
      'users:list',
      '--roles=' . implode(',', self::ROLES),
      "--last-login=-{$threshold}",
      '--fields=uid,mail,roles,user_login',
      '--format=json',
      '--no-interaction',
    ];

    $err->writeln("<comment>Querying users on {$site_count} sites...</comment>");

    // Print only genuine failures as they complete; a site with no qualifying
    // users exits non-zero ("No users found") and is expected, not an error.
    $results = $runner->run($selection, $drush_args, 'prod', NULL, function (int $done, int $total, ?string $key, ?array $result) use ($err) {
      if ($key === NULL || $result === NULL) {
        return;
      }
      if ($result['exit'] !== 0 && !$this->isNoUsersError($result['error'])) {
        $err->writeln("<error>✖</error> [{$done}/{$total}] {$key} (exit {$result['exit']})");
      }
    });

    $writer = $export ? new CsvWriter($this->repoRoot, 'SiteNow-Users-Report', self::HEADERS) : NULL;
    $rows = [];
    $failed = [];

    foreach ($results as $domain => $result) {
      if ($result['exit'] !== 0) {
        // No qualifying users is an expected empty result, not a failure.
        if (!$this->isNoUsersError($result['error'])) {
          $failed[] = $result;
        }
        continue;
      }

      foreach ($this->buildRows($domain, $result['output'], $exclude_users) as $row) {
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
      $io->table(self::HEADERS, $rows);
    }

    if (!empty($failed)) {
      $err->writeln('');
      $err->writeln('<comment>[WARNING] ' . count($failed) . ' site(s) could not be queried:</comment>');
      foreach ($failed as $result) {
        $err->writeln("  {$result['app']}: {$result['site']} (exit {$result['exit']})");
      }
    }

    $io->writeln('');
    $io->writeln('Generated in ' . $this->formatDuration(microtime(TRUE) - $start) . '.');

    return Command::SUCCESS;
  }

  /**
   * Format an elapsed duration for the report footer.
   *
   * @param float $seconds
   *   The elapsed wall-clock seconds.
   *
   * @return string
   *   A human-readable duration: seconds under a minute (e.g. "42.3s"),
   *   otherwise minutes and seconds (e.g. "6m 12s").
   */
  protected function formatDuration(float $seconds): string {
    if ($seconds < 60) {
      return round($seconds, 1) . 's';
    }

    $whole = (int) round($seconds);

    return intdiv($whole, 60) . 'm ' . ($whole % 60) . 's';
  }

  /**
   * Build the report rows for one site's `users:list` output.
   *
   * @param string $domain
   *   The site domain, used as the URL column.
   * @param string $output
   *   The raw `drush users:list --format=json` stdout.
   * @param array $exclude_users
   *   Lowercased email addresses to omit.
   *
   * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
   *   Rows of [email, url, roles, last login], one per qualifying user.
   */
  protected function buildRows(string $domain, string $output, array $exclude_users = []): array {
    $rows = [];

    foreach ($this->decodeUsers($output) as $user) {
      // User 1 is the platform superuser, not an editorial account.
      if (isset($user['uid']) && (int) $user['uid'] === 1) {
        continue;
      }

      $email = trim((string) ($user['mail'] ?? ''));
      if ($email === '' || in_array(strtolower($email), $exclude_users, TRUE)) {
        continue;
      }

      $rows[] = [
        $email,
        $domain,
        $this->extractRoles($user['roles'] ?? []),
        $this->formatLogin($user['user_login'] ?? NULL),
      ];
    }

    return $rows;
  }

  /**
   * Decode `users:list --format=json` output into a list of user records.
   *
   * @param string $output
   *   The raw drush stdout, which may carry leading connection chatter.
   *
   * @return array<int|string, array>
   *   User records keyed by uid, or an empty array when unparseable.
   */
  protected function decodeUsers(string $output): array {
    // Strip any leading connection chatter before the JSON object.
    if (($pos = strpos($output, '{')) !== FALSE) {
      $output = substr($output, $pos);
    }

    $data = json_decode($output, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      return [];
    }

    return $data;
  }

  /**
   * Reduce a user's roles to the qualifying editorial set.
   *
   * `getRoles()` returns every role the user holds (including 'authenticated'
   * and any site-specific roles). Only the editorial roles are reported, in
   * ascending-privilege order.
   *
   * @param mixed $roles
   *   The roles value from the user record: an array, or a delimited string.
   *
   * @return string
   *   The matching editorial roles, comma-separated (e.g. 'editor, publisher').
   */
  protected function extractRoles(mixed $roles): string {
    if (is_string($roles)) {
      $roles = preg_split('/[\n,]+/', $roles);
    }
    if (!is_array($roles)) {
      return '';
    }

    $held = array_map('trim', $roles);

    // array_intersect keeps the order of its first argument, so the column
    // always reads low-to-high privilege regardless of query order.
    return implode(', ', array_values(array_intersect(self::ROLES, $held)));
  }

  /**
   * Format a last-login timestamp as a date.
   *
   * @param mixed $timestamp
   *   The raw `user_login` value (a Unix timestamp).
   *
   * @return string
   *   The date as Y-m-d, or an empty string when there is no login.
   */
  protected function formatLogin(mixed $timestamp): string {
    $timestamp = (int) $timestamp;

    return $timestamp > 0 ? date('Y-m-d', $timestamp) : '';
  }

  /**
   * Determine whether a non-zero result is just "no qualifying users".
   *
   * `users:list` throws (non-zero exit) when no users match its filters. For a
   * fleet report that is an expected empty result, not a query failure.
   *
   * @param string $error
   *   The drush stderr.
   *
   * @return bool
   *   TRUE when the error is the no-users-found case.
   */
  protected function isNoUsersError(string $error): bool {
    return stripos($error, 'No users found') !== FALSE;
  }

}
