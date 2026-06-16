<?php

namespace SiteNow\Command;

use SiteNow\Report\CsvWriter;
use SiteNow\Report\DrushRunner;
use SiteNow\Traits\ParsesListOptions;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Reports which sites have which config splits enabled.
 */
#[AsCommand(
  name: 'report:splits',
  description: 'Report which sites have which config splits enabled.',
  aliases: ['splits'],
)]
class ReportSplitsCommand extends Command {

  use SiteNowCommandsTrait;
  use ParsesListOptions;

  const HEADERS = ['Application', 'Domain', 'Split'];

  // Environmental splits (ci/dev/local/prod/stage) are a proxy for which
  // environment a site is in — not useful in this report.
  const ENV_SPLITS = ['ci', 'dev', 'local', 'prod', 'stage'];

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates blt/manifest.yml and the
   *   CSV export.
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
      ->addOption('split', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated split IDs to filter to (e.g. event,thesis_defense).', '')
      ->addOption('apps', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated app names to filter by (e.g. uiowa02,uiowa03).', '')
      ->addOption('export', NULL, InputOption::VALUE_NONE, 'Export results to a CSV file at the repository root.');
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
    if (!$this->requireSshAgent($io)) {
      return Command::FAILURE;
    }

    $target_splits = $this->parseList($input->getOption('split'));
    $target_apps = $this->parseList($input->getOption('apps'));
    $export = (bool) $input->getOption('export');

    $manifest_path = "{$this->repoRoot}/blt/manifest.yml";
    if (!file_exists($manifest_path)) {
      $io->error("Manifest file not found at {$manifest_path}");
      return Command::FAILURE;
    }
    $manifest = Yaml::parseFile($manifest_path);

    $runner = new DrushRunner();
    $writer = $export ? new CsvWriter($this->repoRoot, 'SiteNow-Splits-Report', self::HEADERS) : NULL;

    // Grouped per-split for table output: $results[split_id][] = [app, domain].
    $results = [];

    // Apps whose results were discarded because at least one site failed to
    // respond. Trustworthy aggregates require every site in an app to answer;
    // a partial app is worse than no app, so we drop it entirely.
    $failed_apps = [];

    foreach ($manifest as $app_name => $domains) {
      if (!empty($target_apps) && !in_array($app_name, $target_apps)) {
        continue;
      }

      $err->writeln("Processing {$app_name}...");

      // Buffer this app's rows so we can discard them wholesale if any site
      // in the app fails to respond.
      $app_rows = [];
      $abort_reason = NULL;

      foreach ($domains as $domain) {
        $err->writeln("  Checking {$domain}...");
        $error = NULL;
        $statuses = $this->getSplitStatuses($runner, $domain, $error);

        if ($statuses === FALSE) {
          $abort_reason = "{$domain} — {$error}";
          $err->writeln("<error>{$app_name} aborted: {$abort_reason}</error>");
          break;
        }

        foreach ($statuses as $split_id => $is_active) {
          if (in_array($split_id, self::ENV_SPLITS)) {
            continue;
          }
          if (!$is_active) {
            continue;
          }
          if (!empty($target_splits) && !in_array($split_id, $target_splits)) {
            continue;
          }
          $app_rows[] = [$app_name, $domain, $split_id];
        }
      }

      if ($abort_reason !== NULL) {
        $failed_apps[$app_name] = $abort_reason;
        continue;
      }

      // Commit this app's rows now that it completed cleanly.
      foreach ($app_rows as $row) {
        if ($writer) {
          $writer->writeRow($row);
        }
        else {
          [, $domain, $split_id] = $row;
          $results[$split_id][] = [$app_name, $domain];
        }
      }
    }

    if ($writer) {
      $io->success("Results exported to {$writer->getPath()}");
    }
    elseif (empty($results)) {
      $io->writeln('No active splits found matching the filters.');
    }
    else {
      ksort($results);
      foreach ($results as $split_id => $rows) {
        $io->writeln('');
        $io->writeln("== {$split_id} ==");
        $io->table(['Application', 'Domain'], $rows);
      }
    }

    if (!empty($failed_apps)) {
      $err->writeln('');
      $err->writeln('<comment>[WARNING] ' . count($failed_apps) . ' application(s) excluded from report due to errors:</comment>');
      foreach ($failed_apps as $app_name => $reason) {
        $err->writeln("  {$app_name}: {$reason}");
      }
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * Get config_split statuses for a multisite (prod).
   *
   * One drush call per site returning all splits — avoids the N+1 round
   * trips that `drush config:get` would require.
   *
   * @param \SiteNow\Report\DrushRunner $runner
   *   The drush runner.
   * @param string $multisite
   *   The multisite domain.
   * @param string|null $error
   *   Out-param populated with a human-readable reason when FALSE is returned.
   *
   * @return array<string, bool>|false
   *   Map of split_id => active. FALSE if drush exited non-zero or returned
   *   no parseable output.
   */
  protected function getSplitStatuses(DrushRunner $runner, string $multisite, ?string &$error = NULL): array|false {
    $alias = $this->getDrushAlias($multisite) . '.prod';
    $php = 'foreach (\\Drupal::configFactory()->listAll("config_split.config_split.") as $n) { echo substr($n, 26) . ":" . (int) \\Drupal::config($n)->get("status") . PHP_EOL; }';
    $result = $runner->run($alias, ['php:eval', $php, '--no-interaction']);

    return $this->parseSplitStatuses($result['output'], $result['exit'], $error, $result['error']);
  }

  /**
   * Parse drush php:eval output into a map of split statuses.
   *
   * @param string $output
   *   The drush stdout.
   * @param int $exit_code
   *   The drush exit code.
   * @param string|null $error
   *   Out-param populated with a human-readable reason when FALSE is returned.
   * @param string $stderr
   *   The drush stderr, used to build the error message on a non-zero exit.
   *
   * @return array<string, bool>|false
   *   Map of split_id => active. FALSE on a non-zero exit or unparseable
   *   output.
   */
  protected function parseSplitStatuses(string $output, int $exit_code, ?string &$error, string $stderr = ''): array|false {
    if ($exit_code !== 0) {
      $source = trim($stderr) !== '' ? $stderr : $output;
      $lines = array_filter(array_map('trim', preg_split('/\R/', $source)), fn($l) => $l !== '');
      $tail = $lines ? end($lines) : '';
      $error = "drush exit {$exit_code}" . ($tail !== '' ? " ({$tail})" : '');
      return FALSE;
    }

    // Parse "<split_id>:<0|1>" lines. Any Drupal/Acquia chatter is skipped.
    $statuses = [];
    foreach (preg_split('/\R/', $output) as $line) {
      $line = trim($line);
      if ($line === '' || !str_contains($line, ':')) {
        continue;
      }
      [$id, $val] = explode(':', $line, 2);
      if ($id !== '' && ($val === '0' || $val === '1')) {
        $statuses[$id] = $val === '1';
      }
    }

    if (empty($statuses)) {
      $error = 'no parseable split status lines in drush output';
      return FALSE;
    }

    return $statuses;
  }

}
