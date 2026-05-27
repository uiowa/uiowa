<?php

namespace SiteNow\Traits;

use Consolidation\Config\Config;
use Consolidation\Config\Loader\ConfigProcessor;
use Consolidation\Config\Loader\YamlConfigLoader;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\SslCertificates;
use Uiowa\Multisite;

/**
 * Helpers for running Robo commands in SiteNow.
 */
trait SiteNowCommandsTrait {

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
    // @todo Move out of BLT.
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
   * Get Acquia Cloud applications sorted by name (natural sort).
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   The Acquia Cloud API client.
   *
   * @return array
   *   Array of ApplicationResponse objects sorted by app name.
   */
  protected function getSortedApplications(Client $client): array {
    $api_applications = new Applications($client);
    $applications = array_values(iterator_to_array($api_applications->getAll()));
    usort($applications, function ($a, $b) {
      $name_a = str_replace('prod:', '', $a->hosting->id);
      $name_b = str_replace('prod:', '', $b->hosting->id);
      return strnatcmp($name_a, $name_b);
    });
    return $applications;
  }

  /**
   * Get the drush alias for a multisite prod environment website.
   *
   * @param string $multisite
   *   The multisite domain.
   *
   * @return string
   *   The drush alias identifier (e.g., 'siteswebcommunity.prod').
   */
  protected function getDrushAlias(string $multisite): string {
    // @todo Move out of BLT.
    return Multisite::getIdentifier('http://' . $multisite);
  }

  /**
   * Determine if the command is running inside the DDEV container.
   *
   * @return bool
   *   TRUE if running in DDEV, FALSE otherwise.
   */
  protected function isDdev(): bool {
    return (bool) getenv('IS_DDEV_PROJECT');
  }

  /**
   * Determine if the command is running on a developer host shell.
   *
   * @return bool
   *   TRUE when not inside DDEV and not on an Acquia Cloud environment.
   */
  protected function isHostShell(): bool {
    return !getenv('IS_DDEV_PROJECT') && !getenv('AH_SITE_ENVIRONMENT');
  }

  /**
   * Gather per-application facts from Acquia Cloud.
   *
   * For each application: its database count and whether its active prod SSL
   * certificate covers the given host. This is the read-only data access that
   * plan-based commands query before deciding on a target application.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   The Acquia Cloud API client.
   * @param array $ssl_parts
   *   Output of Multisite::getSslParts(), with 'sans' and 'related' keys.
   * @param callable|null $on_progress
   *   Optional progress callback invoked per application as
   *   function (string $app_name, int $total): void.
   *
   * @return array
   *   Facts keyed by application name, each with: uuid, name, dbs, has_ssl,
   *   ssl_match, related, sans.
   */
  protected function getApplicationFacts(Client $client, array $ssl_parts, ?callable $on_progress = NULL): array {
    $databases = new Databases($client);
    $environments = new Environments($client);
    $certificates = new SslCertificates($client);

    $applications = $this->getSortedApplications($client);
    $total = count($applications);
    $facts = [];

    foreach ($applications as $app) {
      $name = str_replace('prod:', '', $app->hosting->id);
      if ($on_progress) {
        $on_progress($name, $total);
      }

      $db_count = count($databases->getAll($app->uuid));
      $ssl_match = NULL;
      $related_match = NULL;
      $sans_count = NULL;

      foreach ($environments->getAll($app->uuid) as $env) {
        if ($env->name !== 'prod') {
          continue;
        }
        foreach ($certificates->getAll($env->uuid) as $cert) {
          if (!$cert->flags->active) {
            continue;
          }
          $sans_count = count($cert->domains);
          foreach ($cert->domains as $domain) {
            if ($domain === $ssl_parts['sans']) {
              $ssl_match = $domain;
            }
            elseif ($domain === $ssl_parts['related'] && !$related_match) {
              $related_match = $domain;
            }
          }
        }
      }

      $facts[$name] = [
        'uuid' => $app->uuid,
        'name' => $name,
        'dbs' => $db_count,
        'has_ssl' => $ssl_match !== NULL,
        'ssl_match' => $ssl_match,
        'related' => $related_match,
        'sans' => $sans_count,
      ];
    }

    return $facts;
  }

  /**
   * Determine if SSH agent has keys loaded.
   *
   * @return bool
   *   TRUE if keys are available, FALSE otherwise.
   */
  protected function hasSshAgent(): bool {
    exec('ssh-add -l >/dev/null', $output, $exit_code);
    return $exit_code === 0;
  }

  /**
   * Initialize a CSV export file with headers.
   *
   * @param string $filename_prefix
   *   Prefix for the CSV filename (e.g., 'SiteNow-Domains-Report').
   * @param array $headers
   *   Array of header column names.
   *
   * @return string
   *   The filepath where the CSV file was created.
   */
  protected function initializeCsvExport(string $filename_prefix, array $headers): string {
    $now = date('Ymd-His');
    $filename = "{$filename_prefix}-{$now}.csv";
    $root = $this->getConfigValue('repo.root') ?: getcwd();
    $filepath = "$root/$filename";

    if (file_exists($filepath)) {
      unlink($filepath);
    }
    $this->say("Created export file $filepath");
    $fp = fopen($filepath, 'w+');
    fputcsv($fp, $headers, ',', '"', '\\');
    fclose($fp);

    return $filepath;
  }

}
