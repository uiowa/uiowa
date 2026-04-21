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
   * Get last non-admin user login timestamp via drush alias (prod).
   *
   * @param string $multisite
   *   The multisite domain.
   *
   * @return int|null|false
   *   Unix timestamp if found, NULL if no login data, FALSE if error querying.
   */
  protected function getLastUserLogin(string $multisite): int|null|false {
    $alias = $this->getDrushAlias($multisite) . '.prod';
    $cmd = "drush @{$alias} users:list --no-roles=administrator --format=json --no-interaction < /dev/null 2>&1";
    $output = shell_exec($cmd);

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
        if ($login_time && ($latest_login === NULL || $login_time > $latest_login)) {
          $latest_login = $login_time;
        }
      }
    }

    return $latest_login;
  }

}
