<?php

namespace SiteNow\Report;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Environments;

/**
 * Iterates customer-facing domains across the Acquia application fleet.
 *
 * Centralizes the filtering rules shared by the report commands: skip
 * UIHC-owned applications, strip the 'prod:' hosting prefix, exclude
 * internal Acquia platform domains, and treat 'stage' as 'test' when
 * filtering environments.
 */
class FleetDomains {

  /**
   * Constructs the fleet iterator.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   An authenticated Acquia Cloud API client.
   */
  public function __construct(
    private Client $client,
  ) {}

  /**
   * Get the short application name from an application response.
   *
   * @param object $application
   *   An ApplicationResponse object.
   *
   * @return string
   *   The hosting id without the 'prod:' prefix (e.g. 'uiowa02').
   */
  public static function appName(object $application): string {
    return str_replace('prod:', '', $application->hosting->id);
  }

  /**
   * Normalize an environment name for filtering.
   *
   * Some apps use 'stage' instead of 'test' (e.g. uiowa07); treat them as
   * equivalent so --env=test matches both.
   *
   * @param string $name
   *   The environment name as reported by the API.
   *
   * @return string
   *   The normalized name.
   */
  public static function normalizeEnvName(string $name): string {
    return $name === 'stage' ? 'test' : $name;
  }

  /**
   * Determine if a domain is an internal Acquia platform domain.
   *
   * @param string $domain
   *   The domain to test.
   * @param string $app_name
   *   The short application name (e.g. 'uiowa02').
   * @param string $env_name
   *   The raw (un-normalized) environment name.
   *
   * @return bool
   *   TRUE for platform-internal domains that should not be reported.
   */
  public static function isPlatformDomain(string $domain, string $app_name, string $env_name): bool {
    return str_contains($domain, '.prod.drupal.')
      || str_contains($domain, '.acquia-sites.com')
      || str_starts_with($domain, "{$app_name}.{$env_name}");
  }

  /**
   * Iterate customer-facing domains across the given applications.
   *
   * @param array $applications
   *   ApplicationResponse objects (e.g. from getSortedApplications()).
   * @param array $target_apps
   *   Short app names to include; empty means all non-UIHC apps.
   * @param array $target_envs
   *   Normalized environment names to include (e.g. ['prod']).
   * @param callable|null $on_app
   *   Optional callback invoked per processed app as fn (string $app_name).
   *
   * @return \Generator<array{app: string, env: string, domain: string}>
   *   One row per customer-facing domain. 'env' is the raw API name.
   */
  public function iterate(array $applications, array $target_apps = [], array $target_envs = ['prod'], ?callable $on_app = NULL): \Generator {
    $api_environments = new Environments($this->client);

    foreach ($applications as $application) {
      if ($application->organization->name === 'University of Iowa Healthcare') {
        continue;
      }

      $app_name = self::appName($application);

      if (!empty($target_apps) && !in_array($app_name, $target_apps)) {
        continue;
      }

      if ($on_app) {
        $on_app($app_name);
      }

      /** @var \AcquiaCloudApi\Response\EnvironmentResponse $environment */
      foreach ($api_environments->getAll($application->uuid) as $environment) {
        if (!in_array(self::normalizeEnvName($environment->name), $target_envs)) {
          continue;
        }

        foreach ($environment->domains as $domain) {
          if (self::isPlatformDomain($domain, $app_name, $environment->name)) {
            continue;
          }

          yield [
            'app' => $app_name,
            'env' => $environment->name,
            'domain' => $domain,
          ];
        }
      }
    }
  }

}
