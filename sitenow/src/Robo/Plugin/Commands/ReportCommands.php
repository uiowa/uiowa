<?php

namespace SiteNow\Robo\Plugin\Commands;

use SiteNow\Robo\Traits\SiteNowCommandsTrait;
use AcquiaCloudApi\Endpoints\Environments;
use Robo\Tasks;
use Symfony\Component\Console\Helper\Table;

/**
 * Robo commands for reporting domain information.
 */
class ReportCommands extends Tasks {
  use SiteNowCommandsTrait;

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
   * @option application
   *   Comma-separated list of app names to filter by (e.g. uiowa02,uiowa03).
   */
  public function domains(
    $options = [
      'export' => FALSE,
      'debug' => FALSE,
      'env' => '',
      'application' => '',
    ],
  ) {
    $site_data = [];

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

    // Parse application filter — empty means all applications.
    $target_applications = !empty($options['application'])
      ? array_map('trim', explode(',', $options['application']))
      : [];

    if ($options['export']) {
      $now = date('Ymd-His');
      $filename = "SiteNow-Domains-Report-$now.csv";
      $root = $this->getConfigValue('repo.root') ?: getcwd();
      $filepath = "$root/$filename";

      if (file_exists($filepath)) {
        unlink($filepath);
      }
      $this->say("Created export file $filepath");
      $fp = fopen($filepath, 'w+');
      fputcsv($fp, $headers, ',', '"', '\\');
      fclose($fp);
    }

    $this->say('Starting to check environments.');

    $client = $this->getAcquiaCloudApiClient(
      $this->getConfigValue('uiowa.credentials.acquia.key'),
      $this->getConfigValue('uiowa.credentials.acquia.secret')
    );

    $api_environments = new Environments($client);
    $applications = $this->getSortedApplications($client);

    foreach ($applications as $application) {
      // Skip UIHC applications.
      if ($application->organization->name === 'University of Iowa Healthcare') {
        continue;
      }

      $app_name = str_replace('prod:', '', $application->hosting->id);

      // Skip if not in the requested application list.
      if (!empty($target_applications) && !in_array($app_name, $target_applications)) {
        continue;
      }

      $this->say("Getting environments for $app_name...");

      /** @var \AcquiaCloudApi\Response\EnvironmentResponse $environment */
      foreach ($api_environments->getAll($application->uuid) as $environment) {
        // Some applications use 'stage' instead of 'test' (e.g. uiowa07).
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
            'application' => $app_name,
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
   * Report of inactive SiteNow sites.
   *
   * @command uiowa:report:inactive
   *
   * @option apps
   *   Comma-separated list of app names to filter by (e.g. uiowa,uiowa03).
   * @option threshold
   *   Inactivity threshold (e.g. "1 year", "6 months"). Default: "1 year".
   */
  public function inactive(
    $options = [
      'apps' => '',
      'threshold' => '1 year',
    ],
  ) {
    $site_data = [];
    $now = time();

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

    $headers = ['Application', 'URL', 'Days Since Login', "Inactive: $threshold_period"];

    $this->say('Fetching domains from Acquia Cloud API...');
    $client = $this->getAcquiaCloudApiClient(
      $this->getConfigValue('uiowa.credentials.acquia.key'),
      $this->getConfigValue('uiowa.credentials.acquia.secret')
    );

    $api_environments = new Environments($client);
    $applications = $this->getSortedApplications($client);

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

      $this->say("Processing $app_name...");

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
          $this->say("  Checking $domain...");

          $last_login = $this->getLastUserLogin($domain);

          if ($last_login === FALSE) {
            $days_since_login = 'Error';
            $status = 'Error';
          }
          elseif ($last_login === NULL) {
            $days_since_login = 'Never logged in';
            $status = 'Active';
          }
          else {
            $days_since_login = ceil(($now - $last_login) / 86400);
            $status = ($last_login < $cutoff) ? 'Inactive' : 'Active';
          }

          $site_data[] = [
            'application' => $app_name,
            'url' => $domain,
            'days_since_login' => $days_since_login,
            'inactive' => $status,
          ];
        }
      }
    }

    // Free memory.
    $api_environments = NULL;

    $this->say('Here are the results.');
    $table = new Table($this->output());
    $table->setHeaders($headers);
    $table->setRows($site_data);
    $table->render();
  }

}
