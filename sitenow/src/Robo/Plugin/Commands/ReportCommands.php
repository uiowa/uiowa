<?php

namespace SiteNow\Robo\Plugin\Commands;

use SiteNow\Robo\Traits\SiteNowCommandsTrait;
use AcquiaCloudApi\Endpoints\Environments;
use Robo\ResultData;
use Robo\Tasks;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Robo commands for SiteNow specific reporting.
 */
class ReportCommands extends Tasks {
  use SiteNowCommandsTrait;

  /**
   * Exit code for a report that was generated with incomplete coverage.
   *
   * Distinct from EXITCODE_ERROR (1, command failed outright) so callers
   * can choose to treat a partial-but-accurate report as usable.
   */
  const EXITCODE_PARTIAL = 2;

  /**
   * List domains on PROD environment (default) or specified environments.
   *
   * @command uiowa:report:domains
   *
   * @option export
   *   Whether to export results to a CSV file.
   * @option debug
   *   Enable debug output.
   * @option env
   *   Comma-separated list of environments to filter by (e.g. dev,test).
   * @option apps
   *   Comma-separated list of app names to filter by (e.g. uiowa02,uiowa03).
   */
  public function domains(
    $options = [
      'export' => FALSE,
      'debug' => FALSE,
      'env' => '',
      'apps' => '',
    ],
  ) {
    if (!$this->isDdev()) {
      $this->say('[ERROR] This command must be run inside the DDEV container. Use: ddev exec ./vendor/bin/robo uiowa:report:domains');
      return;
    }

    $site_data = [];
    $filepath = NULL;

    $headers = [
      'Application',
      'Environment',
      'URL',
    ];

    $debug = $options['debug'];

    // Parse env filter — default to prod if not specified.
    $target_environments = !empty($options['env'])
      ? array_map('trim', explode(',', $options['env']))
      : ['prod'];

    // Parse app filter — empty means all apps.
    $target_apps = !empty($options['apps'])
      ? array_map('trim', explode(',', $options['apps']))
      : [];

    if ($options['export']) {
      $filepath = $this->initializeCsvExport('SiteNow-Domains-Report', $headers);
    }

    $this->say('Starting to check environments.');

    $client = $this->getAcquiaCloudApiClient(
      $this->getConfigValue('uiowa.credentials.acquia.key'),
      $this->getConfigValue('uiowa.credentials.acquia.secret')
    );

    $api_environments = new Environments($client);
    $apps = $this->getSortedApplications($client);

    foreach ($apps as $app) {
      // Skip UIHC apps.
      if ($app->organization->name === 'University of Iowa Healthcare') {
        continue;
      }

      $app_name = str_replace('prod:', '', $app->hosting->id);

      // Skip if not in the requested app list.
      if (!empty($target_apps) && !in_array($app_name, $target_apps)) {
        continue;
      }

      $this->say("Getting environments for $app_name...");

      /** @var \AcquiaCloudApi\Response\EnvironmentResponse $environment */
      foreach ($api_environments->getAll($app->uuid) as $environment) {
        // Some apps use 'stage' instead of 'test' (e.g. uiowa07).
        // Treat 'stage' as equivalent to 'test' when filtering environments.
        $env_name = $environment->name;
        if ($env_name === 'stage' && in_array('test', $target_environments)) {
          $env_name = 'test';
        }

        // Only report on specified environments.
        if (!in_array($env_name, $target_environments)) {
          continue;
        }

        $domains = array_values(array_filter(
          $environment->domains,
          function ($domain) use ($app_name, $environment) {
            // Filter out internal Acquia platform domains.
            return !(
              str_contains($domain, '.prod.drupal.') ||
              str_contains($domain, '.acquia-sites.com') ||
              str_starts_with($domain, "$app_name.{$environment->name}")
            );
          }
        ));

        foreach ($domains as $domain) {
          if ($debug) {
            $this->say("Debug: Found domain $domain in {$environment->name}");
          }

          $site = [
            'app' => $app_name,
            'environment' => $environment->name,
            'domain'      => $domain,
          ];

          if ($options['export']) {
            $fp = fopen($filepath, 'a');
            fputcsv($fp, $site, ',', '"', '\\');
            fclose($fp);
          }
          else {
            $site_data[] = $site;
          }
        }
      }
    }

    // Free memory.
    $api_environments = NULL;

    $this->say('Done.');

    if (!$options['export']) {
      $this->say('Here are your results.');
      $table = new Table($this->output());
      $table->setHeaders($headers);
      $table->setRows($site_data);
      $table->render();
    }
    else {
      $this->say("Results exported to $filepath");
    }
  }

  /**
   * Report which sites have which config splits enabled.
   *
   * Reports only active splits by default (i.e. "which sites are running X").
   *
   * Exits 0 when every site was checked, 1 when the command itself fails,
   * and 2 when the report was generated but some sites could not be
   * checked (their split status is unknown).
   *
   * @command uiowa:report:splits
   *
   * @option split
   *   Comma-separated list of split IDs to filter to (e.g. event,thesis_defense).
   * @option apps
   *   Comma-separated list of app names to filter by (e.g. uiowa02,uiowa03).
   * @option export
   *   Whether to export results to a CSV file.
   * @option concurrency
   *   Maximum number of simultaneous drush processes. Defaults to 8 per
   *   app in scope, capped at 32. At most 8 jobs run per app at once
   *   regardless of this value.
   */
  public function splits(
    $options = [
      'split' => '',
      'apps' => '',
      'export' => FALSE,
      'concurrency' => NULL,
    ],
  ) {
    if (!$this->isDdev()) {
      $this->say('[ERROR] This command must be run inside the DDEV container. Use: ddev exec ./vendor/bin/robo uiowa:report:splits');
      return new ResultData(ResultData::EXITCODE_ERROR);
    }
    if (!$this->hasSshAgent()) {
      $this->say("[ERROR] No SSH keys loaded. Please load your SSH keys before running this command.");
      return new ResultData(ResultData::EXITCODE_ERROR);
    }

    $target_splits = !empty($options['split'])
      ? array_map('trim', explode(',', $options['split']))
      : [];

    $target_apps = !empty($options['apps'])
      ? array_map('trim', explode(',', $options['apps']))
      : [];

    $filepath = NULL;
    $headers = ['Application', 'Domain', 'Split'];

    if ($options['export']) {
      $filepath = $this->initializeCsvExport('SiteNow-Splits-Report', $headers);
    }

    // Grouped per-split for table output: $results[split_id][] = [app, domain].
    $results = [];

    // Sites that failed to respond. Their split status is unknown, so
    // absence from the results means "confirmed no split" only for sites
    // that answered; failed sites are listed in a warning instead.
    $failed_sites = [];

    // Environmental splits (ci/dev/local/prod/stage) are a proxy for which
    // environment a site is in — not useful in this report.
    $env_splits = ['ci', 'dev', 'local', 'prod', 'stage'];

    // Use blt/manifest.yml — it's the authoritative SiteNow fleet list and is
    // already deduplicated (www/redirect pairs collapsed), unlike the Acquia
    // API domain list. Top-level keys are Acquia app names; values are arrays
    // of multisite domains.
    $root = $this->getConfigValue('repo.root') ?: getcwd();
    $manifest_path = "$root/blt/manifest.yml";

    if (!file_exists($manifest_path)) {
      $this->say("[ERROR] Manifest file not found at $manifest_path");
      return new ResultData(ResultData::EXITCODE_ERROR);
    }

    $manifest = Yaml::parseFile($manifest_path);

    // Build the full job list up front so every site in every app shares one
    // process pool — parallelizing only within an app would leave the pool
    // underfilled on small apps.
    $jobs = [];
    $job_groups = [];
    $app_domains = [];

    foreach ($manifest as $app_name => $domains) {
      if (!empty($target_apps) && !in_array($app_name, $target_apps)) {
        continue;
      }

      foreach ($domains as $domain) {
        $app_domains[$app_name][] = $domain;
        $jobs["$app_name|$domain"] = $this->getSplitStatusCommand($domain);
        $job_groups["$app_name|$domain"] = $app_name;
      }
    }

    $concurrency = $this->resolveConcurrency($options['concurrency'], count($app_domains));

    $this->say('Checking ' . count($jobs) . " sites ($concurrency at a time)...");

    $raw_results = $this->runProcessPool(
      $jobs,
      $concurrency,
      $this->createProgressRenderer('Checking sites'),
      $job_groups
    );

    foreach ($app_domains as $app_name => $domains) {
      foreach ($domains as $domain) {
        [$exit_code, $output] = $raw_results["$app_name|$domain"];
        $error = NULL;
        $statuses = $this->parseSplitStatuses($output, $exit_code, $error);

        if ($statuses === FALSE) {
          $this->say("[ERROR] $domain failed: $error");
          $failed_sites[] = "$app_name: $domain — $error";
          continue;
        }

        foreach ($statuses as $split_id => $is_active) {
          if (in_array($split_id, $env_splits)) {
            continue;
          }
          if (!$is_active) {
            continue;
          }
          if (!empty($target_splits) && !in_array($split_id, $target_splits)) {
            continue;
          }

          if ($options['export']) {
            $fp = fopen($filepath, 'a');
            fputcsv($fp, [$app_name, $domain, $split_id], ',', '"', '\\');
            fclose($fp);
          }
          else {
            $results[$split_id][] = [$app_name, $domain];
          }
        }
      }
    }

    if ($options['export']) {
      $this->say("Results exported to $filepath");
    }
    elseif (empty($results)) {
      $this->say('No active splits found matching the filters.');
    }
    else {
      ksort($results);
      foreach ($results as $split_id => $rows) {
        $this->say('');
        $this->say("== {$split_id} ==");
        $table = new Table($this->output());
        $table->setHeaders(['Application', 'Domain']);
        $table->setRows($rows);
        $table->render();
      }
    }

    if (!empty($failed_sites)) {
      $this->say('');
      $this->say('[WARNING] ' . count($failed_sites) . ' site(s) could not be checked; their split status is unknown:');
      foreach ($failed_sites as $line) {
        $this->say("  $line");
      }
      return new ResultData(self::EXITCODE_PARTIAL);
    }
  }

  /**
   * Build the drush command that fetches config_split statuses (prod).
   *
   * One drush call per site returning all splits — avoids the N+1 round
   * trips that `drush config:get` would require.
   *
   * @param string $multisite
   *   The multisite domain.
   *
   * @return string
   *   Shell command whose output parses with parseSplitStatuses().
   */
  private function getSplitStatusCommand(string $multisite): string {
    $alias = $this->getDrushAlias($multisite) . '.prod';
    $php = 'foreach (\\Drupal::configFactory()->listAll("config_split.config_split.") as $n) { echo substr($n, 26) . ":" . (int) \\Drupal::config($n)->get("status") . PHP_EOL; }';
    return "drush @{$alias} php:eval " . escapeshellarg($php) . " --no-interaction < /dev/null 2>&1";
  }

  /**
   * Parse getSplitStatusCommand() output into split statuses.
   *
   * @param string $output
   *   Combined stdout/stderr from the drush process.
   * @param int $exit_code
   *   The process exit code.
   * @param string|null $error
   *   Out-param populated with a human-readable reason when FALSE is returned.
   *
   * @return array<string, bool>|false
   *   Map of split_id => active. FALSE if drush exited non-zero or returned
   *   no parseable output.
   */
  private function parseSplitStatuses(string $output, int $exit_code, ?string &$error = NULL): array|false {
    $output_lines = preg_split('/\R/', trim($output)) ?: [];

    if ($exit_code !== 0) {
      $tail = trim((string) end($output_lines));
      $error = "drush exit $exit_code" . ($tail !== '' ? " ($tail)" : '');
      return FALSE;
    }

    // Parse "<split_id>:<0|1>" lines. Drush/Acquia chatter is skipped.
    $statuses = [];
    foreach ($output_lines as $line) {
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

  /**
   * Resolve the process pool concurrency for drush-over-SSH jobs.
   *
   * SSH multiplexing (see .ddev/homeadditions/.ssh/config.d) gives one
   * connection per app server, and Acquia drops the connection when too
   * many concurrent sessions ride it — 8 per app is validated safe, 16 is
   * not. The pool enforces that per-app cap itself, so total concurrency
   * scales with the number of apps in scope, bounded to keep the local
   * process count sane.
   *
   * @param mixed $requested
   *   The --concurrency option value; NULL/empty means use the default.
   * @param int $app_count
   *   Number of distinct apps in scope for this run.
   *
   * @return int
   *   The concurrency to use, always at least 1.
   */
  private function resolveConcurrency(mixed $requested, int $app_count): int {
    if (!empty($requested)) {
      return max(1, (int) $requested);
    }

    return min(32, 8 * max(1, $app_count));
  }

  /**
   * Build a progress callback for runProcessPool().
   *
   * On an interactive console, renders an ASCII spinner with counts and the
   * most recently finished job on a single self-overwriting line. When
   * output is piped (e.g. redirected to a log), falls back to a plain
   * progress line every 25 completions so logs stay short.
   *
   * @param string $label
   *   Text shown before the counts, e.g. "Checking sites".
   *
   * @return callable
   *   Callback matching runProcessPool()'s $on_progress signature.
   */
  private function createProgressRenderer(string $label): callable {
    $spinner = ['|', '/', '-', '\\'];
    $tick = 0;
    $last_reported = 0;
    $latest_shown = '';
    $finished = FALSE;
    $decorated = $this->output()->isDecorated();

    return function (int $done, int $total, ?string $key) use (&$tick, &$last_reported, &$latest_shown, &$finished, $spinner, $decorated, $label) {
      if ($key !== NULL) {
        $latest_shown = $key;
      }

      if ($done >= $total) {
        if (!$finished) {
          $finished = TRUE;
          if ($decorated) {
            $this->output()->write("\r" . str_pad("$label: $done/$total done.", 100) . "\n");
          }
          else {
            $this->say("$label: $done/$total done.");
          }
        }
        return;
      }

      // A retry pass restarts the counts after the main pass finished.
      $finished = FALSE;

      if ($decorated) {
        $frame = $spinner[$tick++ % count($spinner)];
        $line = "$frame $label: $done/$total" . ($latest_shown !== '' ? " — $latest_shown" : '');
        if (strlen($line) > 100) {
          $line = substr($line, 0, 97) . '...';
        }
        $this->output()->write("\r" . str_pad($line, 100));
      }
      elseif ($done - $last_reported >= 25) {
        $last_reported = $done;
        $this->say("$label: $done/$total...");
      }
    };
  }

  /**
   * Run shell commands concurrently with a bounded process pool.
   *
   * The work here is I/O-bound (drush over SSH), so wall-clock time scales
   * down roughly linearly with the concurrency cap.
   *
   * @param array<string, string> $commands
   *   Map of job key => shell command. Each command should redirect stderr
   *   into stdout (2>&1) if callers expect combined output.
   * @param int $concurrency
   *   Maximum number of simultaneous processes.
   * @param callable|null $on_progress
   *   Optional callback invoked as (int $done, int $total, ?string $key)
   *   each time a job finishes ($key set) and once per ~100ms poll tick
   *   ($key NULL, for spinner animation), plus a final call when all jobs
   *   are done. See createProgressRenderer().
   * @param array<string, string> $job_groups
   *   Optional map of job key => group name (e.g. the Acquia app). No more
   *   than $group_cap jobs per group run at once, so one slow or large app
   *   cannot monopolize its multiplexed SSH connection.
   * @param int $group_cap
   *   Maximum simultaneous jobs per group. 8 is the validated safe session
   *   count per Acquia app connection.
   * @param int $retries
   *   How many times to re-run failed (non-zero exit) jobs before accepting
   *   the failure. Drupal bootstraps over SSH are occasionally flaky under
   *   concurrent load, and callers like splits() discard a whole app over a
   *   single failed site.
   *
   * @return array<string, array{0: int, 1: string}>
   *   Map of job key => [exit code, output]. Timed-out jobs get a non-zero
   *   exit code and a timeout message as output.
   */
  private function runProcessPool(array $commands, int $concurrency, ?callable $on_progress = NULL, array $job_groups = [], int $group_cap = 8, int $retries = 1): array {
    $queue = array_keys($commands);
    $total = count($commands);
    $running = [];
    $results = [];
    $group_counts = [];

    while ($queue || $running) {
      // Top up the pool, skipping jobs whose group is at its cap.
      while ($queue && count($running) < $concurrency) {
        $key = NULL;

        foreach ($queue as $i => $candidate) {
          $group = $job_groups[$candidate] ?? NULL;
          if ($group === NULL || ($group_counts[$group] ?? 0) < $group_cap) {
            $key = $candidate;
            unset($queue[$i]);
            break;
          }
        }

        // Every queued job's group is at cap; wait for harvests.
        if ($key === NULL) {
          break;
        }

        $process = Process::fromShellCommandline($commands[$key]);
        $process->setTimeout(300);
        $process->start();
        $running[$key] = $process;

        if (isset($job_groups[$key])) {
          $group_counts[$job_groups[$key]] = ($group_counts[$job_groups[$key]] ?? 0) + 1;
        }
      }

      // Harvest finished processes.
      foreach ($running as $key => $process) {
        try {
          $process->checkTimeout();
        }
        catch (ProcessTimedOutException) {
          $results[$key] = [1, 'process timed out after 300 seconds'];
          unset($running[$key]);
          if (isset($job_groups[$key])) {
            $group_counts[$job_groups[$key]]--;
          }
          if ($on_progress !== NULL) {
            $on_progress(count($results), $total, $key);
          }
          continue;
        }

        if ($process->isRunning()) {
          continue;
        }

        $results[$key] = [(int) $process->getExitCode(), $process->getOutput()];
        unset($running[$key]);
        if (isset($job_groups[$key])) {
          $group_counts[$job_groups[$key]]--;
        }
        if ($on_progress !== NULL) {
          $on_progress(count($results), $total, $key);
        }
      }

      if ($running) {
        if ($on_progress !== NULL) {
          $on_progress(count($results), $total, NULL);
        }
        usleep(100000);
      }
    }

    if ($on_progress !== NULL) {
      $on_progress(count($results), $total, NULL);
    }

    // Re-run failed jobs at reduced concurrency; transient bootstrap
    // failures usually succeed on a quieter second attempt.
    if ($retries > 0) {
      $failed = array_filter($results, fn (array $r) => $r[0] !== 0);

      if (!empty($failed)) {
        $this->say('Retrying ' . count($failed) . ' failed check(s)...');

        $retry_results = $this->runProcessPool(
          array_intersect_key($commands, $failed),
          min($concurrency, 4),
          $on_progress,
          $job_groups,
          $group_cap,
          $retries - 1
        );

        $results = array_merge($results, $retry_results);
      }
    }

    return $results;
  }

  /**
   * Report of inactive SiteNow sites.
   *
   * @command uiowa:report:inactive
   *
   * @option apps
   *   Comma-separated list of app names to filter by (e.g. uiowa,uiowa03).
   * @option threshold
   *   Inactivity threshold (e.g. "1 year", "6 months"). Defaults to 1 year.
   * @option export
   *   Whether to export results to a CSV file.
   * @option concurrency
   *   Maximum number of simultaneous drush processes. Defaults to 8 per
   *   app in scope, capped at 32. At most 8 jobs run per app at once
   *   regardless of this value.
   */
  public function inactive(
    $options = [
      'apps' => '',
      'threshold' => '1 year',
      'export' => FALSE,
      'concurrency' => NULL,
    ],
  ) {
    if (!$this->isDdev()) {
      $this->say('[ERROR] This command must be run inside the DDEV container. Use: ddev exec ./vendor/bin/robo uiowa:report:inactive');
      return;
    }

    if (!$this->hasSshAgent()) {
      $this->say("[ERROR] No SSH keys loaded. Please 'ddev auth ssh' before running this command.");
      return;
    }

    $site_data = [];
    $now = time();
    $filepath = NULL;

    // Parse app filter — empty means all applications.
    $target_apps = !empty($options['apps'])
      ? array_map('trim', explode(',', $options['apps']))
      : [];

    // Parse threshold.
    $threshold_period = trim($options['threshold']);
    $cutoff = strtotime("-{$threshold_period}", $now);
    if ($cutoff === FALSE) {
      $this->say("Error: Could not parse threshold '$threshold_period'");
      return;
    }

    $headers = ['Application', 'URL', 'Days Since Revision', 'Days Since Login', "Login Inactive: $threshold_period"];

    if ($options['export']) {
      $filepath = $this->initializeCsvExport('SiteNow-Inactive-Report', $headers);
    }

    $this->say('Fetching domains from Acquia Cloud API...');
    $client = $this->getAcquiaCloudApiClient(
      $this->getConfigValue('uiowa.credentials.acquia.key'),
      $this->getConfigValue('uiowa.credentials.acquia.secret')
    );

    $api_environments = new Environments($client);
    $applications = $this->getSortedApplications($client);

    // First pass: collect the site list from the API so every site's drush
    // checks can share one process pool.
    $sites = [];

    foreach ($applications as $application) {
      // Skip UIHC applications.
      if ($application->organization->name === 'University of Iowa Healthcare') {
        continue;
      }

      $app_name = str_replace('prod:', '', $application->hosting->id);

      // Skip if not in the requested application list.
      if (!empty($target_apps) && !in_array($app_name, $target_apps)) {
        continue;
      }

      $this->say("Fetching domains for $app_name...");

      /** @var \AcquiaCloudApi\Response\EnvironmentResponse $environment */
      foreach ($api_environments->getAll($application->uuid) as $environment) {
        // Only check PROD environments.
        if ($environment->name !== 'prod') {
          continue;
        }

        $domains = array_values(array_filter(
          $environment->domains,
          function ($domain) use ($app_name, $environment) {
            // Filter out internal Acquia platform domains.
            return !(
              str_contains($domain, '.prod.drupal.') ||
              str_contains($domain, '.acquia-sites.com') ||
              str_starts_with($domain, "$app_name.{$environment->name}")
            );
          }
        ));

        foreach ($domains as $domain) {
          $sites[] = ['app' => $app_name, 'domain' => $domain];
        }
      }
    }

    // Two independent drush checks per site; both go in the pool so a
    // site's login and revision queries also overlap.
    $jobs = [];
    $job_groups = [];
    foreach ($sites as $site) {
      $jobs["login|{$site['domain']}"] = $this->getLastUserLoginCommand($site['domain']);
      $jobs["revision|{$site['domain']}"] = $this->getLastContentRevisionCommand($site['domain']);
      $job_groups["login|{$site['domain']}"] = $site['app'];
      $job_groups["revision|{$site['domain']}"] = $site['app'];
    }

    $app_count = count(array_unique(array_column($sites, 'app')));
    $concurrency = $this->resolveConcurrency($options['concurrency'], $app_count);

    $this->say('Checking ' . count($sites) . " sites ($concurrency drush processes at a time)...");

    $raw_results = $this->runProcessPool(
      $jobs,
      $concurrency,
      $this->createProgressRenderer('Running checks'),
      $job_groups
    );

    foreach ($sites as $site) {
      $app_name = $site['app'];
      $domain = $site['domain'];

      $last_revision = $this->parseLastContentRevision($raw_results["revision|$domain"][1]);

      if ($last_revision === FALSE) {
        $days_since_revision = 'N/A';
      }
      elseif ($last_revision === NULL) {
        $days_since_revision = 'Never';
      }
      else {
        $days_since_revision = ceil(($now - $last_revision) / 86400);
      }

      $last_login = $this->parseLastUserLogin($raw_results["login|$domain"][1]);

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

      $row = [
        $app_name,
        $domain,
        $days_since_revision,
        $days_since_login,
        $status,
      ];

      if ($options['export']) {
        $fp = fopen($filepath, 'a');
        fputcsv($fp, $row, ',', '"', '\\');
        fclose($fp);
      }
      else {
        $site_data[] = [
          'application' => $app_name,
          'url' => $domain,
          'days_since_revision' => $days_since_revision,
          'days_since_login' => $days_since_login,
          'inactive' => $status,
        ];
      }
    }

    // Free memory.
    $api_environments = NULL;

    if ($options['export']) {
      $this->say("Results exported to $filepath");
    }
    else {
      $this->say('Here are the results.');
      $table = new Table($this->output());
      $table->setHeaders($headers);
      $table->setRows($site_data);
      $table->render();
    }
  }

  /**
   * Build the drush command that lists non-admin users (prod).
   *
   * @param string $multisite
   *   The multisite domain.
   *
   * @return string
   *   Shell command whose output parses with parseLastUserLogin().
   */
  private function getLastUserLoginCommand(string $multisite): string {
    $alias = $this->getDrushAlias($multisite) . '.prod';
    return "drush @{$alias} users:list --no-roles=administrator --format=json --no-interaction < /dev/null 2>&1";
  }

  /**
   * Parse getLastUserLoginCommand() output into a last-login timestamp.
   *
   * @param string $output
   *   Combined stdout/stderr from the drush process.
   *
   * @return int|null|false
   *   Unix timestamp if found, NULL if no login data, FALSE if error querying.
   */
  private function parseLastUserLogin(string $output): int|null|false {
    if (empty($output)) {
      return FALSE;
    }

    // Check for drush errors (e.g., alias not found for redirecting domains).
    if (stripos($output, 'could not be found') !== FALSE ||
        stripos($output, 'failed to run') !== FALSE ||
        stripos($output, 'error') !== FALSE ||
        stripos($output, 'exception') !== FALSE) {
      return FALSE;
    }

    // Strip Acquia Cloud connection messages before the JSON.
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

    foreach ($users as $user) {
      if (isset($user['uid']) && $user['uid'] == 1) {
        continue;
      }

      if (!empty($user['login'])) {
        $login_time = strtotime($user['login']);
        // Skip UNIX start time defaults (Dec 31, 1969).
        if ($login_time && $login_time > strtotime('2000-01-01') && ($latest_login === NULL || $login_time > $latest_login)) {
          $latest_login = $login_time;
        }
      }
    }

    return $latest_login;
  }

  /**
   * Build the drush command that fetches the last node revision (prod).
   *
   * Excludes admin (uid 1) edits.
   *
   * @param string $multisite
   *   The multisite domain.
   *
   * @return string
   *   Shell command whose output parses with parseLastContentRevision().
   */
  private function getLastContentRevisionCommand(string $multisite): string {
    $alias = $this->getDrushAlias($multisite) . '.prod';
    return "drush @{$alias} sqlq \"SELECT MAX(revision_timestamp) FROM node_revision WHERE revision_uid != 1\" --no-interaction < /dev/null 2>&1";
  }

  /**
   * Parse getLastContentRevisionCommand() output into a revision timestamp.
   *
   * @param string $output
   *   Combined stdout/stderr from the drush process.
   *
   * @return int|null|false
   *   Unix timestamp if found, NULL if no revisions, FALSE if error querying.
   */
  private function parseLastContentRevision(string $output): int|null|false {
    if (empty($output)) {
      return FALSE;
    }

    // Check for drush errors.
    if (stripos($output, 'could not be found') !== FALSE ||
        stripos($output, 'failed to run') !== FALSE ||
        stripos($output, 'error') !== FALSE ||
        stripos($output, 'exception') !== FALSE) {
      return FALSE;
    }

    // Extract numeric timestamp from output (may include connection messages).
    foreach (explode("\n", $output) as $line) {
      $line = trim($line);
      if (is_numeric($line)) {
        $timestamp = (int) $line;
        return $timestamp > 0 ? $timestamp : NULL;
      }
    }

    return FALSE;
  }

}
