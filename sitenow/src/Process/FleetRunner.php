<?php

namespace SiteNow\Process;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use SiteNow\Config\Manifest;
use SiteNow\Utility\Multisite;

/**
 * Runs one drush command across manifest-selected sites.
 *
 * This is the shared layer between fleet commands and the process pool:
 * consumers (multisite:execute, report commands) get back plain arrays of
 * {site, app, exit, output, error} to branch on or parse — never rendered
 * text. The manifest (sitenow/manifest.yml, maintained by every provision) is
 * the source of truth for which sites exist on which application.
 *
 * Sites are reached over SSH by site alias, or by local drush when the site
 * belongs to the application and environment this process runs on. See
 * runsLocally().
 */
class FleetRunner {

  /**
   * Total-concurrency ceiling regardless of how many apps are in scope.
   */
  const MAX_CONCURRENCY = 32;

  /**
   * Validated safe concurrent SSH sessions per multiplexed app connection.
   *
   * Also caps concurrent local drush processes per app.
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
   * Drupal's own default site directory, provisioned automatically.
   *
   * The one directory Multisite::getIdentifier() never names an alias file
   * after: this site is set up automatically rather than through per-site
   * provisioning, so its alias file is named after the directory
   * (default.site.yml) instead of an identifier derived from whichever
   * domain sites.php happens to alias to it (e.g. demo.sitenow.uiowa.edu).
   * See aliasIdentifier().
   */
  const DEFAULT_SITE_DIRECTORY = 'default';

  /**
   * Absolute path to the manifest of applications and their site domains.
   */
  private string $manifestPath;

  /**
   * Absolute path to the repo-wide drush.yml.
   */
  private string $drushConfigPath;

  /**
   * Absolute path to the directory holding the per-site drush alias files.
   */
  private string $aliasDir;

  /**
   * Parsed alias environments, keyed by "<site id>.<env>".
   *
   * A selection can run to four figures of sites, and both the transport
   * decision and the local --uri read the same alias file, so each one is
   * parsed at most once.
   *
   * @var array<string, array|null>
   */
  private array $aliasCache = [];

  /**
   * The sites.php host => directory aliases, read once.
   *
   * @var array<string, string>|null
   */
  private ?array $siteDirectories = NULL;

  /**
   * The Acquia application this process is running on, or NULL if not on one.
   */
  private ?string $localApp;

  /**
   * The Acquia environment this process is running in, or NULL.
   */
  private ?string $localEnv;

  /**
   * Constructs the runner.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates the drush binary fleet
   *   jobs run, plus the manifest and drush config unless overridden.
   * @param string|null $manifestPath
   *   Manifest location, defaulting to sitenow/manifest.yml under the
   *   repository root.
   * @param string|null $drushConfigPath
   *   Location of the drush.yml whose ssh.options fleet jobs inherit,
   *   defaulting to drush/drush.yml under the repository root.
   * @param string|null $localApp
   *   The Acquia application this process is running on, defaulting to
   *   AH_SITE_GROUP. Injected by tests.
   * @param string|null $localEnv
   *   The Acquia environment this process is running in, defaulting to
   *   AH_SITE_ENVIRONMENT. Injected by tests.
   * @param string|null $aliasDir
   *   Location of the per-site drush alias files, defaulting to drush/sites
   *   under the repository root. Injected by tests.
   */
  public function __construct(
    private string $repoRoot,
    ?string $manifestPath = NULL,
    ?string $drushConfigPath = NULL,
    ?string $localApp = NULL,
    ?string $localEnv = NULL,
    ?string $aliasDir = NULL,
  ) {
    $this->manifestPath = $manifestPath ?? Manifest::defaultPath($repoRoot);
    $this->drushConfigPath = $drushConfigPath ?? "{$repoRoot}/drush/drush.yml";
    $this->aliasDir = $aliasDir ?? "{$repoRoot}/drush/sites";
    $this->localApp = $localApp ?? (getenv('AH_SITE_GROUP') ?: NULL);
    $this->localEnv = $localEnv ?? (getenv('AH_SITE_ENVIRONMENT') ?: NULL);
  }

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

    if (file_exists($this->drushConfigPath)) {
      try {
        $config = Yaml::parseFile($this->drushConfigPath);
        if (is_string($config['ssh']['options'] ?? NULL)) {
          $base = $config['ssh']['options'];
        }
      }
      catch (ParseException) {
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

    foreach ($selection as $app => $domains) {
      foreach ($domains as $domain) {
        $jobs[$domain] = $this->jobArgv($app, $domain, $env, $drush_args);
        $groups[$domain] = $app;
      }
    }

    return ['jobs' => $jobs, 'groups' => $groups];
  }

  /**
   * Whether one site's job runs on this machine rather than over SSH.
   *
   * True only for a site on the application and environment this process is
   * running on. Anything else is a different machine.
   *
   * The environment is settled by the alias, not by comparing $env to
   * AH_SITE_ENVIRONMENT: the alias key and the Acquia environment name are
   * not the same string everywhere. Alias key 'test' is environment 'test' on
   * uiowa through uiowa06 but 'stage' on uiowa07, uiowa08 and uiowa09, so a
   * direct comparison would call the running environment remote on those
   * three and demand SSH keys that aren't there.
   *
   * @param string $app
   *   The application the site belongs to.
   * @param string $domain
   *   The site domain, which locates its alias file.
   * @param string $env
   *   The target environment (e.g. 'prod').
   *
   * @return bool
   *   TRUE when the job runs locally.
   */
  public function runsLocally(string $app, string $domain, string $env): bool {
    return $this->localAlias($app, $domain, $env) !== NULL;
  }

  /**
   * Whether any site in a selection will be reached over SSH.
   *
   * Callers gate the SSH agent precondition on this; an entirely local run
   * needs no keys.
   *
   * @param array<string, array<int, string>> $selection
   *   Map of app name => site domains, as returned by select().
   * @param string $env
   *   The target environment (e.g. 'prod').
   *
   * @return bool
   *   TRUE when at least one job needs SSH.
   */
  public function hasRemoteJobs(array $selection, string $env = 'prod'): bool {
    foreach ($selection as $app => $domains) {
      foreach ($domains as $domain) {
        if (!$this->runsLocally($app, $domain, $env)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Build one site's drush argv, local or remote.
   *
   * Remote jobs are addressed by site alias. Any alias with a host key is
   * remote to drush — site-alias tests only for that key's presence
   * (SiteAliasTrait::isRemote()) and never compares the host to this machine
   * — so an alias naming this very environment still opens an SSH connection
   * back to itself. Authentication isn't the obstacle there: Acquia's own
   * drush-cron.sh runs its environment's alias with no agent loaded, and
   * `drush @uiowa07.prod status` succeeds on uiowa07.prod. Host key
   * verification is, and it took down the scheduled site-count job on every
   * application whose sites have to be queried individually
   * (uiowa/uiowa#10101). Local jobs drop the alias and address the site by
   * --root and --uri, so no connection is opened to fail.
   *
   * --root is this process's own docroot rather than the alias's, so the job
   * runs the code that is actually deployed here. --uri comes from the alias,
   * which is the only place the site's per-environment hostname is recorded:
   * the manifest holds production domains, and pointing a dev or test job at
   * one would hand the site a production base_url.
   *
   * @param string $app
   *   The application the site belongs to.
   * @param string $domain
   *   The site domain.
   * @param string $env
   *   The target environment (e.g. 'prod').
   * @param array $drush_args
   *   Drush arguments, each a separate element.
   *
   * @return array<int, string>
   *   The full argv for the job.
   */
  protected function jobArgv(string $app, string $domain, string $env, array $drush_args): array {
    if ($alias = $this->localAlias($app, $domain, $env)) {
      // The process pool launches jobs without a working directory.
      return array_merge(
        $this->drushCommand(),
        ["--root={$this->repoRoot}/docroot", "--uri={$alias['uri']}"],
        $drush_args,
      );
    }

    $alias = $this->aliasIdentifier($domain) . '.' . $env;

    return array_merge(
      $this->drushCommand(),
      ["@{$alias}", '--ssh-options=' . $this->sshOptions()],
      $drush_args,
    );
  }

  /**
   * One site's alias definition, when it names this very environment.
   *
   * The alias's 'user' is the Acquia site user — "uiowa07.stage" — which is
   * AH_SITE_GROUP and AH_SITE_ENVIRONMENT joined, and so answers both halves
   * of the question at once. Returning the definition rather than a boolean
   * lets the local branch read the hostname from the same parse.
   *
   * @param string $app
   *   The application the site belongs to.
   * @param string $domain
   *   The site domain.
   * @param string $env
   *   The target environment (e.g. 'prod').
   *
   * @return array|null
   *   The alias environment definition, or NULL when the job is not local.
   */
  protected function localAlias(string $app, string $domain, string $env): ?array {

    // Off Acquia nothing is local; a different application never is, and the
    // manifest's app names are AH_SITE_GROUP values, so this settles most of
    // a fleet-wide selection without opening a file.
    if ($this->localApp === NULL || $this->localEnv === NULL || $app !== $this->localApp) {
      return NULL;
    }

    $alias = $this->aliasEnv($domain, $env);
    if (!isset($alias['user'], $alias['uri']) || $alias['user'] !== "{$this->localApp}.{$this->localEnv}") {
      return NULL;
    }

    return $alias;
  }

  /**
   * Read one environment out of a site's drush alias file.
   *
   * @param string $domain
   *   The site domain.
   * @param string $env
   *   The alias key to read (e.g. 'prod').
   *
   * @return array|null
   *   The alias environment definition, or NULL when the file is missing,
   *   unparseable, or has no such environment.
   */
  protected function aliasEnv(string $domain, string $env): ?array {
    $id = $this->aliasIdentifier($domain);
    $key = "{$id}.{$env}";

    if (!array_key_exists($key, $this->aliasCache)) {
      $this->aliasCache[$key] = NULL;
      $path = "{$this->aliasDir}/{$id}.site.yml";

      if (file_exists($path)) {
        try {
          $alias = Yaml::parseFile($path);
          if (is_array($alias) && is_array($alias[$env] ?? NULL)) {
            $this->aliasCache[$key] = $alias[$env];
          }
        }
        catch (ParseException) {
          // An alias file that doesn't parse breaks the remote transport too;
          // leaving this NULL sends the job over SSH, where drush reports the
          // broken alias better than a crash here would.
        }
      }
    }

    return $this->aliasCache[$key];
  }

  /**
   * The identifier a site's drush alias file is named after.
   *
   * Multisite::getIdentifier() derives this from the domain and matches the
   * alias filename for every site except the application's own default site
   * (DEFAULT_SITE_DIRECTORY): that one is provisioned automatically rather
   * than through per-site provisioning, so its alias file is named after the
   * directory (default.site.yml), not an identifier derived from whichever
   * domain sites.php happens to alias to it. Skipping this for every other
   * site keeps the fleet-wide selection cheap: siteDirectory() only opens
   * sites.php on the first call of any kind, and most fleets never hit this
   * one site to begin with.
   *
   * @param string $domain
   *   The site domain.
   *
   * @return string
   *   The alias identifier, i.e. the alias file's basename.
   */
  protected function aliasIdentifier(string $domain): string {
    return $this->siteDirectory($domain) === self::DEFAULT_SITE_DIRECTORY
      ? self::DEFAULT_SITE_DIRECTORY
      : Multisite::getIdentifier('http://' . $domain);
  }

  /**
   * Resolve a site domain to its multisite directory, per sites.php.
   *
   * Mirrors Drupal's own aliasing: a host with no entry resolves to a
   * same-named directory, and sites.php maps the handful of exceptions,
   * notably the application's own default site — reached at a domain like
   * demo.sitenow.uiowa.edu but living in docroot/sites/default.
   *
   * @param string $domain
   *   The site domain.
   *
   * @return string
   *   The multisite directory name.
   */
  protected function siteDirectory(string $domain): string {
    if ($this->siteDirectories === NULL) {
      $sites = [];
      $sites_file = "{$this->repoRoot}/docroot/sites/sites.php";
      if (file_exists($sites_file)) {
        // sites.php populates $sites with host => directory aliases.
        include $sites_file;
      }
      $this->siteDirectories = $sites;
    }

    return $this->siteDirectories[$domain] ?? $domain;
  }

  /**
   * The command prefix that launches drush, before any drush arguments.
   *
   * The project's own drush, rather than whatever `drush` resolves to on
   * PATH: that varies between machines and is absent from the host shell
   * entirely, so a bare command name would make the fleet's drush version an
   * accident of the environment. Running the binary composer installed keeps
   * fleet jobs on the version the project is tested against, on the host and
   * inside DDEV alike.
   *
   * Launched through PHP explicitly so display_errors can be set: a host PHP
   * newer than the container's emits deprecation notices from the local vendor
   * tree, and the CLI SAPI writes those to stdout, where they land in the
   * per-site output consumers parse. Sending them to stderr keeps parsed output
   * clean without hiding them.
   *
   * @return array<int, string>
   *   The argv prefix: the PHP binary, its ini overrides, then drush.
   */
  protected function drushCommand(): array {
    return [PHP_BINARY, '-d', 'display_errors=stderr', "{$this->repoRoot}/vendor/bin/drush"];
  }

  /**
   * Check that the drush command is registered before fanning out.
   *
   * Runs `drush help <command>` against one canary site — the first site of
   * the selection — which verifies registration without executing anything.
   * Command availability can vary per site (site-specific modules, config
   * splits), so a canary failure is advisory: the command may exist on
   * other sites, but far more often it's a typo about to fail everywhere.
   *
   * @param array<string, array<int, string>> $selection
   *   Map of app name => site domains, as returned by select().
   * @param string $command
   *   The drush command name to check.
   * @param string $env
   *   The environment suffix for the site alias (e.g. 'prod').
   *
   * @return array{site: string, exit: int, output: string, error: string}|null
   *   NULL when the canary recognizes the command; otherwise the canary
   *   site and its drush result.
   */
  public function preflight(array $selection, string $command, string $env = 'prod'): ?array {
    if (empty($selection)) {
      return NULL;
    }

    $app = array_key_first($selection);
    $domains = $selection[$app];
    if (empty($domains)) {
      return NULL;
    }

    $site = $domains[0];
    $result = $this->runCanary($site, $this->jobArgv($app, $site, $env, ['help', $command]));

    return $result['exit'] === 0 ? NULL : ['site' => $site] + $result;
  }

  /**
   * Execute the canary job. Split out so tests can fake the drush call.
   *
   * @param string $site
   *   The canary site domain.
   * @param array<int, string> $argv
   *   The drush argv to run.
   *
   * @return array{exit: int, output: string, error: string}
   *   The canary result.
   */
  protected function runCanary(string $site, array $argv): array {
    $pool = new ProcessPool(1, 1, 120, 0);

    return $pool->run([$site => $argv])[$site];
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
