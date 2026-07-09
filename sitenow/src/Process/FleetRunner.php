<?php

namespace SiteNow\Process;

use Symfony\Component\Yaml\Yaml;
use Uiowa\Multisite;

/**
 * Runs one drush command across manifest-selected sites and returns
 * structured per-site results.
 *
 * This is the shared layer between fleet commands and the process pool:
 * consumers (multisite:execute, report commands) get back plain arrays of
 * {site, app, exit, output, error} to branch on or parse — never rendered
 * text. The manifest (blt/manifest.yml, maintained by every provision) is
 * the source of truth for which sites exist on which application.
 */
class FleetRunner {

  /**
   * Total-concurrency ceiling regardless of how many apps are in scope.
   */
  const MAX_CONCURRENCY = 32;

  /**
   * Validated safe concurrent SSH sessions per multiplexed app connection.
   */
  const PER_APP_CAP = 8;

  /**
   * Constructs the runner.
   *
   * @param string $manifestPath
   *   Absolute path to blt/manifest.yml.
   */
  public function __construct(
    private string $manifestPath,
  ) {}

  /**
   * Select sites from the manifest.
   *
   * @param array $apps
   *   App names to include (empty = all apps).
   * @param array $exclude
   *   Site domains to exclude.
   *
   * @return array<string, array<int, string>>
   *   Map of app name => site domains.
   *
   * @throws \RuntimeException
   *   When the manifest is missing or an app name is unknown.
   */
  public function select(array $apps = [], array $exclude = []): array {
    if (!file_exists($this->manifestPath)) {
      throw new \RuntimeException("Manifest file not found at {$this->manifestPath}");
    }
    $manifest = Yaml::parseFile($this->manifestPath) ?? [];

    if ($unknown = array_diff($apps, array_keys($manifest))) {
      throw new \RuntimeException('Unknown application(s): ' . implode(', ', $unknown));
    }

    $selected = [];
    foreach ($manifest as $app => $domains) {
      if (!empty($apps) && !in_array($app, $apps, TRUE)) {
        continue;
      }
      $domains = array_values(array_diff($domains, $exclude));
      if (!empty($domains)) {
        $selected[$app] = $domains;
      }
    }

    return $selected;
  }

  /**
   * Build the per-site drush argv jobs for a selection.
   *
   * Public so callers can render a dry run without executing anything.
   *
   * @param array<string, array<int, string>> $selection
   *   Map of app name => site domains, as returned by select().
   * @param array $drush_args
   *   Drush arguments, each a separate element (e.g. ['cr'] or
   *   ['sql:query', 'SELECT COUNT(*) FROM node']).
   * @param string $env
   *   The environment suffix for the site alias (e.g. 'prod').
   *
   * @return array{jobs: array<string, array<int, string>>, groups: array<string, string>}
   *   Pool-ready jobs and groups, keyed by site domain.
   */
  public function buildJobs(array $selection, array $drush_args, string $env = 'prod'): array {
    $jobs = [];
    $groups = [];

    foreach ($selection as $app => $domains) {
      foreach ($domains as $domain) {
        $alias = Multisite::getIdentifier('http://' . $domain) . '.' . $env;
        $jobs[$domain] = array_merge(['drush', "@{$alias}"], $drush_args);
        $groups[$domain] = $app;
      }
    }

    return ['jobs' => $jobs, 'groups' => $groups];
  }

  /**
   * Run a drush command against every site in a selection.
   *
   * @param array<string, array<int, string>> $selection
   *   Map of app name => site domains, as returned by select().
   * @param array $drush_args
   *   Drush arguments, each a separate element.
   * @param string $env
   *   The environment suffix for the site alias (e.g. 'prod').
   * @param int|null $concurrency
   *   Total concurrency cap; NULL scales with the number of apps in scope
   *   (PER_APP_CAP per app, at most MAX_CONCURRENCY).
   * @param callable|null $on_progress
   *   Optional progress callback; see ProcessPool::run().
   *
   * @return array<string, array{site: string, app: string, exit: int, output: string, error: string}>
   *   Per-site results keyed by site domain.
   */
  public function run(array $selection, array $drush_args, string $env = 'prod', ?int $concurrency = NULL, ?callable $on_progress = NULL): array {
    ['jobs' => $jobs, 'groups' => $groups] = $this->buildJobs($selection, $drush_args, $env);
    $concurrency = $concurrency ?? $this->defaultConcurrency(count($selection));

    $pool = new ProcessPool($concurrency, self::PER_APP_CAP);
    $raw = $pool->run($jobs, $groups, $on_progress);

    $results = [];
    foreach ($raw as $domain => $result) {
      $results[$domain] = [
        'site' => $domain,
        'app' => $groups[$domain],
      ] + $result;
    }

    return $results;
  }

  /**
   * Default total concurrency for a run.
   *
   * @param int $app_count
   *   Number of distinct apps in scope.
   *
   * @return int
   *   PER_APP_CAP per app in scope, capped at MAX_CONCURRENCY.
   */
  public function defaultConcurrency(int $app_count): int {
    return min(self::MAX_CONCURRENCY, self::PER_APP_CAP * max(1, $app_count));
  }

}
