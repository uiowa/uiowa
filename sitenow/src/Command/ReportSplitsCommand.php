<?php

namespace SiteNow\Command;

use SiteNow\Process\FleetRunner;
use SiteNow\Report\CsvWriter;
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
  use DescribesDrushFailures;

  const HEADERS = ['Application', 'Domain', 'Split'];

  // Config name prefix shared by every config_split entity. Stripped to leave
  // the bare split id (e.g. 'config_split.config_split.event' -> 'event').
  const SPLIT_CONFIG_PREFIX = 'config_split.config_split.';

  // Environmental splits (ci/dev/local/prod/stage) are a proxy for which
  // environment a site is in, not useful in this report.
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
      ->addOption('exclude', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated site domains to skip.', '')
      ->addOption('export', NULL, InputOption::VALUE_NONE, 'Export results to a CSV file at the repository root.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();

    $target_splits = $this->parseList($input->getOption('split'));
    $target_apps = $this->parseList($input->getOption('apps'));
    $exclude = $this->parseList($input->getOption('exclude'));
    $export = (bool) $input->getOption('export');

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

    $err->writeln("<comment>Reading split status on {$site_count} sites...</comment>");

    // The pool retries failures and reports progress per attempt, so a line
    // here describes one attempt, not the site's outcome: a site that fails
    // its first attempt and succeeds on retry still lands in the report. The
    // end-of-run summary is the verdict, being built from final results.
    $drush_args = ['php:eval', $this->splitStatusScript(), '--no-interaction'];
    $results = $runner->run($selection, $drush_args, 'prod', NULL, function (int $done, int $total, ?string $key, ?array $result) use ($err) {
      if ($key === NULL || $result === NULL) {
        return;
      }
      if ($result['exit'] !== 0) {
        $err->writeln("<error>✖</error> [{$done}/{$total}] attempt failed: {$key} — " . $this->failureReason($result));
      }
    });

    // Buffered per app so a failed site can discard its app's rows wholesale.
    // Trustworthy aggregates require every site in an app to answer; a partial
    // app is worse than no app, so we drop it entirely.
    $app_rows = [];
    $failed_apps = [];

    // Walked in manifest order rather than completion order, so the report
    // reads the same whichever site the pool happens to finish first.
    foreach ($selection as $app_name => $domains) {
      foreach ($domains as $domain) {
        $result = $results[$domain];

        if ($result['exit'] !== 0) {
          $failed_apps[$app_name] ??= "{$domain} — " . $this->failureReason($result);
          continue;
        }

        $statuses = $this->parseSplitStatuses($result['output']);
        if (empty($statuses)) {
          $failed_apps[$app_name] ??= "{$domain} — no parseable split status lines in drush output";
          continue;
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
          $app_rows[$app_name][] = [$app_name, $domain, $split_id];
        }
      }
    }

    $writer = $export ? new CsvWriter($this->repoRoot, 'SiteNow-Splits-Report', self::HEADERS, [
      $target_apps ? implode('+', $target_apps) : 'all-apps',
      $target_splits ? implode('+', $target_splits) : '',
    ]) : NULL;

    // Grouped per split for table output: $rows[split_id][] = [app, domain].
    $rows = [];

    foreach (array_diff_key($app_rows, $failed_apps) as $committed) {
      foreach ($committed as [$app_name, $domain, $split_id]) {
        if ($writer) {
          $writer->writeRow([$app_name, $domain, $split_id]);
        }
        else {
          $rows[$split_id][] = [$app_name, $domain];
        }
      }
    }

    if ($writer) {
      $io->success("Results exported to {$writer->getPath()}");
    }
    elseif (empty($rows)) {
      $io->writeln('No active splits found matching the filters.');
    }
    else {
      ksort($rows);
      foreach ($rows as $split_id => $split_rows) {
        $io->writeln('');
        $io->writeln("== {$split_id} ==");
        $io->table(['Application', 'Domain'], $split_rows);
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
   * The PHP evaluated on each site to list its splits and their status.
   *
   * One drush call per site returning every split, rather than the N+1 round
   * trips `drush config:get` would need.
   *
   * @return string
   *   PHP source echoing one "<split_id>:<0|1>" line per config_split entity.
   */
  protected function splitStatusScript(): string {
    // Built by concatenation so the prefix and its length stay in sync; the
    // single-quoted segments keep $n literal for evaluation on the remote site.
    $prefix = self::SPLIT_CONFIG_PREFIX;
    $offset = strlen($prefix);

    return 'foreach (\\Drupal::configFactory()->listAll("' . $prefix . '") as $n) { echo substr($n, ' . $offset . ') . ":" . (int) \\Drupal::config($n)->get("status") . PHP_EOL; }';
  }

  /**
   * Parse drush php:eval output into a map of split statuses.
   *
   * @param string $output
   *   The drush stdout.
   *
   * @return array<string, bool>
   *   Map of split_id => active, empty when the output held no status lines.
   */
  protected function parseSplitStatuses(string $output): array {
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

    return $statuses;
  }

}
