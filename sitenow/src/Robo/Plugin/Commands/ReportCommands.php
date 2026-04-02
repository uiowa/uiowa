<?php

namespace SiteNow\Robo\Plugin\Commands;

use Consolidation\Config\Config;
use Consolidation\Config\Loader\ConfigProcessor;
use Consolidation\Config\Loader\YamlConfigLoader;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use Robo\Tasks;
use Symfony\Component\Console\Helper\Table;

/**
 * Robo commands for reporting domain information.
 */
class ReportCommands extends Tasks {

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

    $api_applications = new Applications($client);
    $api_environments = new Environments($client);

    foreach ($api_applications->getAll() as $application) {
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
    $api_applications = NULL;
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
   * Build and return an Acquia Cloud API v2 client.
   *
   * @param string $key
   *   The API key (UUID) from cloud.acquia.com/a/profile/tokens.
   * @param string $secret
   *   The API secret from cloud.acquia.com/a/profile/tokens.
   *
   * @return \AcquiaCloudApi\Connector\Client
   *   An authenticated Acquia Cloud API client.
   */
  protected function getAcquiaCloudApiClient(string $key, string $secret): Client {
    $connector = new Connector([
      'key'    => $key,
      'secret' => $secret,
    ]);

    return Client::factory($connector);
  }

  /**
   * Get a config value by key from blt/local.blt.yml.
   *
   * @param string $key
   *   Dot-separated config key, e.g. 'uiowa.credentials.acquia.key'.
   *
   * @return mixed
   *   The config value, or NULL if not found.
   */
  protected function getConfigValue(string $key): mixed {
    $blt_local = getcwd() . '/blt/local.blt.yml';

    if (!file_exists($blt_local)) {
      return NULL;
    }

    $config = new Config();
    $loader = new YamlConfigLoader();
    $processor = new ConfigProcessor();
    $processor->extend($loader->load($blt_local));
    $config->replace($processor->export());

    return $config->get($key);
  }

}
