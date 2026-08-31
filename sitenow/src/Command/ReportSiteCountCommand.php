<?php

namespace SiteNow\Command;

use SiteNow\Process\FleetRunner;
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
 * Reports each application's site count, split by sitenow_v2 status.
 *
 * Built for the weekly Acquia Cloud cron job that emails osc-web@uiowa.edu a
 * site count per application.
 * --totals-only serves applications that never have v2 sites.
 */
#[AsCommand(
  name: 'report:count',
  description: "Report each application's site count, split by sitenow_v2 status.",
  aliases: ['count'],
)]
class ReportSiteCountCommand extends Command {

  use SiteNowCommandsTrait;
  use ParsesListOptions;
  use DescribesDrushFailures;

  /**
   * Exit code for a run that completed but had per-site failures.
   *
   * Distinct from FAILURE (1, nothing could be counted at all).
   */
  const EXITCODE_PARTIAL = 2;

  /**
   * The config_split entity queried for each site's v2/v3 status.
   *
   * Not v2 is equivalent to v3.
   */
  const SPLIT_CONFIG_NAME = 'config_split.config_split.sitenow_v2';

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates sitenow/manifest.yml.
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
      ->addOption('apps', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated app names to report on (e.g. uiowa02,uiowa03). Defaults to all apps; pinned to the running application on Acquia Cloud.', '')
      ->addOption('env', NULL, InputOption::VALUE_REQUIRED, 'Target environment: dev, test, or prod.', 'prod')
      ->addOption('totals-only', NULL, InputOption::VALUE_NONE, "Report only each application's total site count from the manifest, skipping the sitenow_v2 remote query entirely.")
      ->setHelp(<<<'HELP'
For each application, reports its total site count and how many of those
sites have the sitenow_v2 config split enabled. A site with no sitenow_v2
split counts as v3.

A site that fails to answer is excluded from the v2/v3 breakdown and named
explicitly. The exit code reflects this: 0 when every site answered,
2 when some did not, and 1 when nothing could be counted at all.

--totals-only skips the remote query and reports the manifest's site count
per application. Intended for an application that never has v2 sites.

On Acquia Cloud (a scheduled job, or an interactive shell on a hosted
environment), --apps is pinned to the application actually running the
command, same as multisite:execute. Sites on that application's own
environment are queried by local drush, so no SSH keys are needed there;
reaching any other environment still requires a loaded agent.

Examples:
  # Weekly cron, from a scheduled job on the application itself:
  ./sn report:count

  # Weekly cron on an application with no v2 sites:
  ./sn report:count --totals-only

  # From a workstation, across specific applications:
  ./sn report:count --apps=uiowa02,uiowa03
HELP);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();

    $apps = $this->parseList($input->getOption('apps'));
    $env = $input->getOption('env');

    if (!$this->requireEnvironment($io, $env)) {
      return Command::FAILURE;
    }

    $apps = $this->restrictToRunningApp($apps, $err);
    if ($apps === NULL) {
      return Command::FAILURE;
    }

    $runner = new FleetRunner($this->repoRoot);

    try {
      $selection = $runner->select($apps, []);
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

    if ((bool) $input->getOption('totals-only')) {
      foreach ($selection as $app_name => $domains) {
        $io->writeln("{$app_name}: " . count($domains) . ' sites total');
      }
      return Command::SUCCESS;
    }

    if ($runner->hasRemoteJobs($selection, $env) && !$this->requireSshAgent($io)) {
      return Command::FAILURE;
    }

    $err->writeln("<comment>Tallying v2/v3 status on {$site_count} sites...</comment>");

    $drush_args = ['php:eval', $this->splitStatusScript()];
    $results = $runner->run($selection, $drush_args, $env, NULL, function (int $done, int $total, ?string $key, ?array $result) use ($err) {
      if ($key === NULL || $result === NULL || $result['exit'] === 0) {
        return;
      }
      $err->writeln("<error>✖</error> [{$done}/{$total}] attempt failed: {$key} — " . $this->failureReason($result));
    });

    $any_reachable = FALSE;
    $any_failed = FALSE;

    // Walked in manifest order so the report reads the same regardless of
    // which site the pool happens to finish first.
    foreach ($selection as $app_name => $domains) {
      $tally = $this->tally($domains, $results);

      if ($tally['reachable'] > 0) {
        $any_reachable = TRUE;
      }

      if ($tally['failed']) {
        $any_failed = TRUE;
        $line = "{$app_name}: {$tally['reachable']} of {$tally['total']} sites reporting ({$tally['v2']} v2, {$tally['v3']} v3) — unreachable: " . implode(', ', $tally['failed']);
      }
      else {
        $line = "{$app_name}: {$tally['total']} sites total ({$tally['v2']} v2, {$tally['v3']} v3)";
      }

      if ($tally['v2_sites']) {
        $line .= ' — v2: ' . implode(', ', $tally['v2_sites']);
      }

      $io->writeln($line);
    }

    if (!$any_reachable) {
      return Command::FAILURE;
    }

    return $any_failed ? self::EXITCODE_PARTIAL : Command::SUCCESS;
  }

  /**
   * Tally one application's site count and sitenow_v2 breakdown.
   *
   * @param array<int, string> $domains
   *   The application's site domains, from the manifest selection.
   * @param array<string, array{exit: int, output: string, error: string}> $results
   *   Per-site drush results, keyed by domain.
   *
   * @return array{total: int, reachable: int, v2: int, v3: int, v2_sites: array<int, string>, failed: array<int, string>}
   *   The tally. 'v2' and 'v3' are counted only from sites that answered;
   *   'v2_sites' names the ones that came back v2, so they can be listed
   *   alongside the count. 'failed' names the ones that didn't answer, so a
   *   failure is never silently folded into either count.
   */
  protected function tally(array $domains, array $results): array {
    $v2_sites = [];
    $failed = [];

    foreach ($domains as $domain) {
      $result = $results[$domain];

      if ($result['exit'] !== 0) {
        $failed[] = $domain;
        continue;
      }

      if (trim($result['output']) === '1') {
        $v2_sites[] = $domain;
      }
    }

    $total = count($domains);
    $reachable = $total - count($failed);

    return [
      'total' => $total,
      'reachable' => $reachable,
      'v2' => count($v2_sites),
      'v3' => $reachable - count($v2_sites),
      'v2_sites' => $v2_sites,
      'failed' => $failed,
    ];
  }

  /**
   * The PHP evaluated on each site to read its sitenow_v2 split status.
   *
   * A site with no sitenow_v2 split registered at all still answers here:
   * get('status') on a nonexistent config object returns NULL, which casts
   * to 0 (v3) — matching config_split's own inactive-by-default behavior,
   * and the assumption the weekly count has always made.
   *
   * @return string
   *   PHP source echoing "1" (v2) or "0" (v3).
   */
  protected function splitStatusScript(): string {
    return 'echo (int) \\Drupal::config(\'' . self::SPLIT_CONFIG_NAME . '\')->get(\'status\');';
  }

}
