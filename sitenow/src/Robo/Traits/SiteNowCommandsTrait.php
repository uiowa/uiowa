<?php

namespace SiteNow\Robo\Traits;

use Consolidation\Config\Config;
use Consolidation\Config\Loader\ConfigProcessor;
use Consolidation\Config\Loader\YamlConfigLoader;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
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
