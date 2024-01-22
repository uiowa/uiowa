<?php

namespace Uiowa\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\EnvironmentDetector;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Helper\Table;
use Uiowa\Blt\AcquiaCloudApiTrait;
use Uiowa\InspectorTrait;
use Uiowa\Multisite;

/**
 * Global multisite commands.
 */
class ReportCommands extends BltTasks {

  use AcquiaCloudApiTrait;
  use InspectorTrait;

  /**
   * Create analytics report.
   *
   * Generates a report of websites
   * with their Google Analytics and Google Tag Manager ids.
   *
   * @command uiowa:report:analytics
   *
   * @aliases ura
   *
   * @throws \Robo\Exception\TaskException
   */
  public function analytics($options = ['export' => FALSE, 'debug' => FALSE]) {
    $env = EnvironmentDetector::getAhEnv() ?: 'local';

    $site_data = [];

    $headers = [
      'Application',
      'URL',
      'Version',
      'Status',
      'GA Property IDs',
      'GTM Container IDs',
    ];

    $debug = $options['debug'];

    $application_data = $this->getDomainsKeyedByApplication();

    if (!empty($application_data)) {
      // Create the file for exporting locally.
      if ($options['export'] && $env == 'local') {
        $now = date('Ymd-His');
        $filename = "SiteNow-Report-Analytics-$now.csv";
        $root = $this->getConfigValue('repo.root');
        $filepath = "$root/$filename";

        if (file_exists($filepath)) {
          unlink($filepath);
        }
        $this->say("Created export file $filepath");
        $fp = fopen($filepath, 'w+');
        fputcsv($fp, $headers);
        fclose($fp);
      }

      $this->say('Starting to check domains.');
      foreach ($application_data as $machine_name => $data) {
        $this->say("Processing domains for $machine_name...");
        foreach ($data['domains'] as $domain) {
          $this->say("Processing $domain");
          $site = [
            'application' => $machine_name,
            'domain' => $domain,
            'version' => 'V1, custom, or collegiate',
            'status' => 'active',
            'ga_property_ids' => '',
            'gtm_container_ids' => '',
          ];

          // Run 'drush status' to see if the site exists.
          $result = $this->getDrushTask($debug)
            ->interactive(FALSE)
            ->alias("$machine_name.prod")
            ->ansi(FALSE)
            ->drush('status')
            ->option('uri', $domain)
            ->run();

          // If the site doesn't exist, skip to the next record.
          if (str_contains($result->getMessage(), 'Drupal Settings File   :  MISSING')
            || str_contains($result->getMessage(), 'Fatal error')) {
            $site['status'] = 'inactive';
          }
          else {

            // SiteNow V2/V3.
            if (in_array($machine_name, $this->getD9ApplicationList())) {
              // Check if V2 split is enabled to determine version.
              $result = $this->getDrushTask($debug)
                ->interactive(FALSE)
                ->alias("$machine_name.prod")
                ->ansi(FALSE)
                ->drush('config:get')
                ->args(['config_split.config_split.sitenow_v2', 'status'])
                ->option('uri', $domain)
                ->run();

              $site['version'] = str_contains(trim($result->getMessage()), ': false') ? 'V3' : 'V2';

              // Run 'drush config:get google_analytics.settings account'.
              $result = $this->getDrushTask($debug)
                ->interactive(FALSE)
                ->alias("$machine_name.prod")
                ->ansi(FALSE)
                ->drush('config:get')
                ->args(['google_analytics.settings', 'account'])
                ->option('uri', $domain)
                ->run();

              // If the output contains the variable name then it found a
              // result, and we store that in the appropriate key for later
              // output.
              if (str_contains($result->getMessage(), "'google_analytics.settings:account': ")) {
                $output = str_replace("'google_analytics.settings:account': ", '', $result->getMessage());
                $site['ga_property_ids'] = trim(trim($output), "'");
              }

              $result = $this->getDrushTask($debug)
                ->interactive(FALSE)
                ->alias("$machine_name.prod")
                ->ansi(FALSE)
                ->drush('uiowa:get:gtm-containers')
                ->option('uri', $domain)
                ->run();

              $site['gtm_container_ids'] = trim($result->getMessage());
            }
            // SiteNow V1, custom, and collegiate.
            else {
              foreach ([
                'ga_property_ids' => 'googleanalytics_account',
                'gtm_container_ids' => 'google_tag_container_id',
              ] as $key => $variable) {
                // Run 'drush vget $variable'.
                $result = $this->getDrushTask($debug)
                  ->interactive(FALSE)
                  ->alias("$machine_name.prod")
                  ->ansi(FALSE)
                  ->drush('variable:get')
                  ->args($variable)
                  ->option('uri', $domain)
                  ->run();

                // If the output contains the variable name then it found a
                // result, and we store that in the appropriate key for later
                // output.
                if (str_contains($result->getMessage(), "$variable: ")) {
                  $output = str_replace("$variable: ", '', $result->getMessage());
                  $site[$key] = trim(trim($output), "'");
                }
              }
            }
          }
          if ($options['export'] && $env == 'local') {
            // Output to CSV file and copy to local filesystem.
            $fp = fopen($filepath, 'a');
            fputcsv($fp, $site);
            fclose($fp);
            $this->say("Updated $filepath");
          }
          else {
            $site_data[] = $site;
          }
        }
      }
      $this->say('Done');

      if (!$options['export']) {
        $this->say('Here are your results.');
        $table = new Table($this->output);

        $table->setHeaders($headers);
        $table->setRows($site_data);
        $table->render();
      }
    }
    else {
      $this->say('No domains were found for the supplied application.');
    }
  }

  /**
   * Proof of concept to generate a list of last logins for sites.
   *
   * @command uiowa:report:last-login
   *
   * @aliases urll
   *
   * @throws \Robo\Exception\TaskException
   */
  public function lastLogin($options = ['export' => FALSE, 'debug' => FALSE]) {
    $env = EnvironmentDetector::getAhEnv() ?: 'local';

    $site_data = [];

    $headers = [
      'Application',
      'URL',
      'Version',
      'Status',
      'Last Login',
    ];

    $debug = $options['debug'];

    $user_table = 'users';

    $application_data = $this->getDomainsKeyedByApplication();

    if (!empty($application_data)) {
      // Create the file for exporting locally.
      if ($options['export'] && $env == 'local') {
        $now = date('Ymd-His');
        $filename = "SiteNow-Report-Login-$now.csv";
        $root = $this->getConfigValue('repo.root');
        $filepath = "$root/$filename";

        if (file_exists($filepath)) {
          unlink($filepath);
        }
        $this->say("Created export file $filepath");
        $fp = fopen($filepath, 'w+');
        fputcsv($fp, $headers);
        fclose($fp);
      }

      $this->say('Starting to check domains.');
      foreach ($application_data as $machine_name => $data) {
        $this->say("Processing domains for $machine_name...");
        foreach ($data['domains'] as $domain) {
          $this->say("Processing $domain");
          $site = [
            'application' => $machine_name,
            'domain' => $domain,
            'version' => 'V1, custom, or collegiate',
            'status' => 'active',
            'last_login' => '',
          ];

          // Run 'drush status' to see if the site exists.
          $result = $this->getDrushTask($debug)
            ->interactive(FALSE)
            ->alias("$machine_name.prod")
            ->ansi(FALSE)
            ->drush('status')
            ->option('uri', $domain)
            ->run();

          // If the site doesn't exist, skip to the next record.
          if (str_contains($result->getMessage(), 'Drupal Settings File   :  MISSING')
            || str_contains($result->getMessage(), 'Fatal error')) {
            $site['status'] = 'inactive';
          }
          else {

            // SiteNow V2/V3.
            if (in_array($machine_name, $this->getD9ApplicationList())) {
              $user_table = 'users_field_data';
              // Check if V2 split is enabled to determine version.
              $result = $this->getDrushTask($debug)
                ->interactive(FALSE)
                ->alias("$machine_name.prod")
                ->ansi(FALSE)
                ->drush('config:get')
                ->args(['config_split.config_split.sitenow_v2', 'status'])
                ->option('uri', $domain)
                ->run();

              $site['version'] = str_contains(trim($result->getMessage()), ': false') ? 'V3' : 'V2';
            }

            // Get the last login.
            $result = $this->taskDrush()
              ->alias("$machine_name.prod")
              ->ansi(FALSE)
              ->drush('sqlq')
              ->args("SELECT login FROM $user_table ORDER BY login DESC LIMIT 1")
              ->option('uri', $domain)
              ->run();

            $site['last_login'] = date('m/d/Y', (int) trim($result->getMessage()));
          }
          if ($options['export'] && $env == 'local') {
            // Output to CSV file and copy to local filesystem.
            $fp = fopen($filepath, 'a');
            fputcsv($fp, $site);
            fclose($fp);
            $this->say("Updated $filepath");
          }
          else {
            $site_data[] = $site;
          }
        }
      }
      $this->say('Done');

      if (!$options['export']) {
        $this->say('Here are your results.');
        $table = new Table($this->output);

        $table->setHeaders($headers);
        $table->setRows($site_data);
        $table->render();
      }
    }
    else {
      $this->say('No domains were found for the supplied application.');
    }
  }

  /**
   * Generate a list of users/websites for sending a EOL communication.
   *
   * @command uiowa:report:v1-standard-list
   *
   * @throws \Robo\Exception\TaskException
   */
  public function v1StandardCommsList($options = ['export' => FALSE, 'debug' => FALSE]) {
    $env = EnvironmentDetector::getAhEnv() ?: 'local';

    $site_data = [];

    $headers = [
      'email',
      'domain',
    ];

    $debug = $options['debug'];

    $application_data = $this->getDomainsKeyedByApplication([
      'uiowa701',
      'uiowa702',
      'uiowa703',
    ]);

    $root = $this->getConfigValue('repo.root');
    $sites = Multisite::getAllSites($root);

    if (!empty($application_data)) {
      // Create the file for exporting locally.
      if ($options['export'] && $env == 'local') {
        $now = date('Ymd-His');
        $filename = "SiteNow-v1-standard-user-list-$now.csv";
        $root = $this->getConfigValue('repo.root');
        $filepath = "$root/$filename";

        if (file_exists($filepath)) {
          unlink($filepath);
        }
        $this->say("Created export file $filepath");
        $fp = fopen($filepath, 'w+');
        fputcsv($fp, $headers);
        fclose($fp);
      }

      $this->say('Starting to check domains.');
      foreach ($application_data as $machine_name => $data) {
        $this->say("Processing domains for $machine_name...");
        foreach ($data['domains'] as $domain) {
          if (in_array($domain, $sites)) {
            $this->say("Skipping $domain because a site has been requested.");
            continue;
          }
          $this->say("Processing $domain");

          // Run 'drush status' to see if the site exists.
          $result = $this->getDrushTask($debug)
            ->interactive(FALSE)
            ->alias("$machine_name.prod")
            ->ansi(FALSE)
            ->drush('status')
            ->option('uri', $domain)
            ->run();

          $status_message = $result->getMessage();

          // If the site doesn't exist, skip to the next record.
          if (str_contains($status_message, 'Drupal Settings File   :  MISSING')
            || str_contains($status_message, 'Fatal error')) {
            continue;
          }
          else {
            if (!str_contains($status_message, 'sitenow_standard_profile')) {
              continue;
            }

            // Get the last login.
            $result = $this->taskDrush()
              ->alias("$machine_name.prod")
              ->ansi(FALSE)
              ->drush('sqlq')
              ->args("SELECT DISTINCT(mail) from `users` JOIN `users_roles` ON users.uid = users_roles.uid JOIN `role` ON users_roles.rid = role.rid WHERE users.status = 1 AND users.access > 0 AND users.mail <> '' AND role.name IN ('editor', 'webmaster')")
              ->option('uri', $domain)
              ->run();

            $emails_message = trim($result->getMessage());

            if ($emails_message !== '') {
              $emails = explode("\n", $emails_message);
              foreach ($emails as $email) {
                $record = [$email, $domain];
                if ($options['export'] && $env == 'local') {
                  // Output to CSV file and copy to local filesystem.
                  $fp = fopen($filepath, 'a');
                  fputcsv($fp, $record);
                  fclose($fp);
                  $this->say("Updated $filepath");
                }
                else {
                  $site_data[] = $record;
                }
              }
            }
          }
        }
      }
      $this->say('Done');

      if (!$options['export']) {
        $this->say('Here are your results.');
        $table = new Table($this->output);

        $table->setHeaders($headers);
        $table->setRows($site_data);
        $table->render();
      }
    }
    else {
      $this->say('No domains were found for the supplied application.');
    }
  }

  /**
   * Helper method to provide a drush task with suppressed output or not.
   *
   * @param bool $debug
   *   Whether to print output for debugging purposes.
   *
   * @return \Acquia\Blt\Robo\Tasks\DrushTask
   *   The modified drush task.
   */
  protected function getDrushTask(bool $debug = FALSE) {
    $task = $this->taskDrush();

    if (!$debug) {
      $task->printOutput(FALSE)
        ->printMetadata(FALSE);
    }

    return $task;
  }

  /**
   * Helper method to retrieve a list of domains keyed by application.
   */
  protected function getDomainsKeyedByApplication($list = [], $exclude = FALSE): array {

    /** @var \AcquiaCloudApi\Connector\Client $client */
    $client = $this->getAcquiaCloudApiClient($this->getConfigValue('uiowa.credentials.acquia.key'), $this->getConfigValue('uiowa.credentials.acquia.secret'));

    /** @var \AcquiaCloudApi\Endpoints\Environments $environments */
    $api_applications = new Applications($client);

    /** @var \AcquiaCloudApi\Endpoints\Environments $environments */
    $api_environments = new Environments($client);

    $application_data = [];
    // Compile the list of domains to check.
    foreach ($api_applications->getAll() as $application) {
      // Skip UIHC applications.
      if ($application->organization->name === 'University of Iowa Healthcare') {
        continue;
      }

      $app_name = str_replace('prod:', '', $application->hosting->id);

      if (!empty($list)) {
        if ($exclude) {
          if (in_array($app_name, $list)) {
            continue;
          }
        }
        else {
          if (!in_array($app_name, $list)) {
            continue;
          }
        }
      }

      // Use the application machine name for reporting.
      $this->say("Getting domains for $app_name...");

      $application_data[$app_name] = [];

      /** @var \AcquiaCloudApi\Response\EnvironmentResponse $environment */
      foreach ($api_environments->getAll($application->uuid) as $environment) {
        // Only want to proceed if this is production.
        if ($environment->name !== 'prod') {
          continue;
        }

        $application_data[$app_name]['domains'] = array_values(array_filter($environment->domains, function ($domain) use ($app_name) {
          return !(str_contains($domain, '.prod.drupal.') || str_starts_with($domain, "$app_name.prod"));
        }));
      }
    }
    $api_applications = NULL;
    $api_environments = NULL;

    ksort($application_data);

    return $application_data;
  }

  /**
   * Helper method to return a list of D9 applications.
   *
   * @return string[]
   *   The list of D9 applications.
   */
  protected function getD9ApplicationList(): array {
    return [
      'uiowa',
      'uiowa01',
      'uiowa02',
      'uiowa03',
      'uiowa04',
      'uiowa05',
    ];
  }

}
