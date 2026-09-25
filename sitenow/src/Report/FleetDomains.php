<?php

namespace SiteNow\Report;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Environments;
use SiteNow\Utility\Multisite;

/**
 * Iterates customer-facing domains across the Acquia application fleet.
 *
 * Centralizes the filtering rules shared by the report commands: skip
 * UIHC-owned applications, strip the 'prod:' hosting prefix, exclude
 * internal Acquia platform domains, and resolve each application's drush
 * alias environments (dev/test/prod) to Acquia's own environment names
 * when filtering, since 'test' is called 'stage' on some applications.
 */
class FleetDomains {

  /**
   * Organization whose applications the domain reports leave alone.
   */
  const EXCLUDED_ORGANIZATION = 'University of Iowa Healthcare';

  /**
   * Constructs the fleet iterator.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   An authenticated Acquia Cloud API client.
   * @param string $repoRoot
   *   Absolute path to the repository root. Used to resolve applications'
   *   drush alias environment names.
   */
  public function __construct(
    private Client $client,
    private string $repoRoot,
  ) {}

  /**
   * Determine whether an application is in scope for the domain reports.
   *
   * @param object $application
   *   An ApplicationResponse object.
   *
   * @return bool
   *   FALSE for applications owned by the excluded organization.
   */
  public static function isReportable(object $application): bool {
    return $application->organization->name !== self::EXCLUDED_ORGANIZATION;
  }

  /**
   * The short names of every application the reports would consider.
   *
   * Lets a caller reject an unknown --apps value up front. Scoped to
   * reportable applications so that every name this accepts can actually
   * produce rows: an out-of-scope application would be silently skipped
   * during iteration, which is the outcome the check exists to prevent.
   *
   * @param array $applications
   *   ApplicationResponse objects (e.g. from getSortedApplications()).
   *
   * @return array<int, string>
   *   Short application names.
   */
  public static function reportableAppNames(array $applications): array {
    $names = [];

    foreach ($applications as $application) {
      if (self::isReportable($application)) {
        $names[] = self::appName($application);
      }
    }

    return $names;
  }

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
   * Determine whether an environment's raw API name satisfies a request.
   *
   * $target_envs are drush alias environment names (dev/test/prod); the
   * API's raw environment name does not always match, since some
   * applications call the 'test' environment 'stage'. Each target is
   * resolved to that application's actual Acquia name via
   * [[Multisite::getCloudEnvName]] before comparing, so the divergence is
   * handled from the drush alias rather than assumed.
   *
   * @param string $app_name
   *   The short application name (e.g. 'uiowa09').
   * @param string $raw_env_name
   *   The environment name as reported by the API.
   * @param array $target_envs
   *   Requested drush alias environment names (e.g. ['test']).
   *
   * @return bool
   *   TRUE if $raw_env_name is the Acquia name for any of $target_envs.
   */
  protected function matchesTargetEnv(string $app_name, string $raw_env_name, array $target_envs): bool {
    foreach ($target_envs as $target_env) {
      $cloud_name = Multisite::getCloudEnvName(Multisite::aliasDir($this->repoRoot), $app_name, $target_env);

      if ($raw_env_name === $cloud_name) {
        return TRUE;
      }
    }

    return FALSE;
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
      if (!self::isReportable($application)) {
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
        if (!$this->matchesTargetEnv($app_name, $environment->name, $target_envs)) {
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
