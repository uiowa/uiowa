<?php

namespace SiteNow\Process;

use Symfony\Component\Yaml\Yaml;
use Uiowa\Multisite;

/**
 * Runs one drush command across manifest-selected sites.
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
   * SSH options enabling connection multiplexing, per fleet invocation only.
   *
   * Appended (via sshOptions()) to each drush process's --ssh-options so
   * that only fleet runs multiplex: everyday drush commands stay on stock
   * SSH with no shared state. The first connection to an app server
   * authenticates and becomes the master; the rest of the fleet rides it as
   * sessions (sshd caps these around 10 — PER_APP_CAP stays under that, and
   * over-cap requests fall back to a direct connection). The master
   * self-closes 60 seconds after its last session ends.
   */
  const MUX_OPTIONS = '-o ControlMaster=auto -o ControlPath=~/.ssh/cm-%C -o ControlPersist=60';

  /**
   * Constructs the runner.
   *
   * @param string $manifestPath
   *   Absolute path to blt/manifest.yml.
   * @param string|null $drushConfigPath
   *   Absolute path to the repo-wide drush.yml, whose ssh.options fleet
   *   jobs inherit. NULL falls back to drush's own default.
   */
  public function __construct(
    private string $manifestPath,
    private ?string $drushConfigPath = NULL,
  ) {}

  /**
   * Compose the ssh options fleet drush processes run with.
   *
   * Drush's --ssh-options REPLACES the configured ssh.options rather than
   * appending to it, so the repo-wide base (drush/drush.yml: agent
   * forwarding, PasswordAuthentication=no) is read and restated here. Fleet
   * jobs run with exactly what every other drush command uses, plus the
   * multiplexing options.
   *
   * @return string
   *   The composed ssh options string.
   */
  public function sshOptions(): string {

    // Drush's own default when no ssh.options is configured anywhere.
    $base = '-o PasswordAuthentication=no';

    if ($this->drushConfigPath !== NULL && file_exists($this->drushConfigPath)) {
      try {
        $config = Yaml::parseFile($this->drushConfigPath);
        if (is_string($config['ssh']['options'] ?? NULL)) {
          $base = $config['ssh']['options'];
        }
      }
      catch (\Symfony\Component\Yaml\Exception\ParseException) {
        // A drush.yml that doesn't parse breaks every drush command; the
        // per-site drush errors will say so better than a crash here.
      }
    }

    return $base . ' ' . self::MUX_OPTIONS;
  }

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
   *   When the manifest is missing or malformed, or an app name is unknown.
   */
  public function select(array $apps = [], array $exclude = []): array {
    if (!file_exists($this->manifestPath)) {
      throw new \RuntimeException("Manifest file not found at {$this->manifestPath}");
    }

    // Yaml::parseFile() throws on malformed YAML (its ParseException is a
    // \RuntimeException), but a truncated or hand-edited file can parse
    // cleanly into the wrong shape — reject that here instead of fataling
    // on a TypeError below.
    $manifest = Yaml::parseFile($this->manifestPath) ?? [];
    if (!is_array($manifest)) {
      throw new \RuntimeException("Manifest at {$this->manifestPath} is not a map of app => site domains.");
    }
    foreach ($manifest as $app => $domains) {
      if (!is_array($domains)) {
        throw new \RuntimeException("Manifest entry '{$app}' is not a list of site domains.");
      }
    }

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
    $ssh_option = '--ssh-options=' . $this->sshOptions();

    foreach ($selection as $app => $domains) {
      foreach ($domains as $domain) {
        $alias = Multisite::getIdentifier('http://' . $domain) . '.' . $env;
        $jobs[$domain] = array_merge(['drush', "@{$alias}", $ssh_option], $drush_args);
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
