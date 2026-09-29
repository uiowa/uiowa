<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use Drupal\Tests\UnitTestCase;
use SiteNow\Report\FleetDomains;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the report commands' fleet-domain filtering rules.
 *
 * Covers the pure static helpers FleetDomains exposes for selecting
 * customer-facing domains (platform-domain exclusion, app scoping) and its
 * environment-matching, which resolves each application's drush alias to
 * decide whether the API's raw environment name satisfies a --env request.
 * No Acquia API access.
 *
 * @group unit
 */
class FleetDomainsTest extends UnitTestCase {

  /**
   * Scratch directory holding fixture drush alias files.
   *
   * @var string
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/sn-fleetdomains-' . uniqid();
    mkdir("{$this->dir}/drush/sites", 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    (new Filesystem())->remove($this->dir);
    parent::tearDown();
  }

  /**
   * Write a fixture drush alias file for an application.
   *
   * @param string $app
   *   The application (AH_SITE_GROUP).
   * @param string $test_user
   *   The 'test' environment's user field, e.g. 'uiowa09.stage'.
   */
  private function writeAlias(string $app, string $test_user): void {
    file_put_contents("{$this->dir}/drush/sites/{$app}.site.yml", Yaml::dump([
      'dev' => ['user' => "{$app}.dev"],
      'test' => ['user' => $test_user],
      'prod' => ['user' => "{$app}.prod"],
    ], 4, 2));
  }

  /**
   * An unauthenticated client; matchesTargetEnv() never calls the API.
   */
  private function client(): Client {
    // Constructing a Connector emits a deprecation on PHP 8.4, which PHPUnit
    // reports as unexpected output and marks the test risky.
    $reporting = error_reporting(error_reporting() & ~E_DEPRECATED);

    try {
      return Client::factory(new Connector(['key' => 'test', 'secret' => 'test']));
    }
    finally {
      error_reporting($reporting);
    }
  }

  /**
   * A FleetDomains instance exposing the protected matchesTargetEnv().
   *
   * Rooted at the scratch directory holding the fixture alias files.
   */
  private function fleet(): FleetDomains {
    return new class($this->client(), $this->dir) extends FleetDomains {

      /**
       * Exposes matchesTargetEnv().
       */
      public function pubMatchesTargetEnv(string $app_name, string $raw_env_name, array $target_envs): bool {
        return $this->matchesTargetEnv($app_name, $raw_env_name, $target_envs);
      }

    };
  }

  /**
   * Internal Acquia platform domains are excluded.
   *
   * @dataProvider platformDomainProvider
   */
  public function testIsPlatformDomain(string $domain, string $app, string $env, bool $expected): void {
    $this->assertSame($expected, FleetDomains::isPlatformDomain($domain, $app, $env));
  }

  /**
   * Cases for platform-domain detection.
   */
  public static function platformDomainProvider(): array {
    return [
      'acquia load balancer' => ['uiowa02.prod.drupal.acquia-sites.com', 'uiowa02', 'prod', TRUE],
      'acquia-sites domain' => ['something.acquia-sites.com', 'uiowa02', 'prod', TRUE],
      'app-env prefix prod' => ['uiowa02.prod.foo', 'uiowa02', 'prod', TRUE],
      'app-env prefix dev' => ['uiowa03.dev', 'uiowa03', 'dev', TRUE],
      'customer www domain' => ['www.tippie.uiowa.edu', 'uiowa02', 'prod', FALSE],
      'customer bare domain' => ['vote.uiowa.edu', 'uiowa02', 'prod', FALSE],
      'other app prefix is not platform' => ['uiowa03.prod.foo', 'uiowa02', 'prod', FALSE],
    ];
  }

  /**
   * A requested environment matches the application's own Acquia name for it.
   *
   * Resolved through the drush alias, not assumed.
   *
   * @dataProvider matchesTargetEnvProvider
   */
  public function testMatchesTargetEnv(string $app, string $test_user, string $raw_env_name, array $target_envs, bool $expected): void {
    $this->writeAlias($app, $test_user);

    $this->assertSame($expected, $this->fleet()->pubMatchesTargetEnv($app, $raw_env_name, $target_envs));
  }

  /**
   * Cases for environment matching, across a uniform and a divergent app.
   */
  public static function matchesTargetEnvProvider(): array {
    return [
      'uniform app: raw test satisfies requested test' => ['uiowa04', 'uiowa04.test', 'test', ['test'], TRUE],
      'uniform app: raw stage does not satisfy requested test' => ['uiowa04', 'uiowa04.test', 'stage', ['test'], FALSE],
      'divergent app: raw stage satisfies requested test' => ['uiowa09', 'uiowa09.stage', 'stage', ['test'], TRUE],
      'divergent app: raw test does not satisfy requested test' => ['uiowa09', 'uiowa09.stage', 'test', ['test'], FALSE],
      'dev is uniform regardless of the app' => ['uiowa09', 'uiowa09.stage', 'dev', ['dev'], TRUE],
      'any of several requested envs may match' => ['uiowa09', 'uiowa09.stage', 'stage', ['dev', 'test'], TRUE],
      'no match among the requested envs' => ['uiowa09', 'uiowa09.stage', 'stage', ['dev', 'prod'], FALSE],
    ];
  }

  /**
   * Without an alias file, the requested env name is used as-is.
   *
   * An application missing from the drush alias tree (or not yet checked out)
   * falls back to treating the request literally rather than refusing to
   * match anything.
   */
  public function testMatchesTargetEnvFallsBackWithoutAnAliasFile(): void {
    $fleet = $this->fleet();

    $this->assertTrue($fleet->pubMatchesTargetEnv('unknownapp', 'test', ['test']));
    $this->assertFalse($fleet->pubMatchesTargetEnv('unknownapp', 'stage', ['test']));
  }

  /**
   * The short app name drops the 'prod:' hosting prefix.
   */
  public function testAppName(): void {
    $application = (object) ['hosting' => (object) ['id' => 'prod:uiowa02']];
    $this->assertSame('uiowa02', FleetDomains::appName($application));
  }

  /**
   * Applications owned by the excluded organization are out of scope.
   */
  public function testIsReportable(): void {
    $this->assertTrue(FleetDomains::isReportable($this->application('uiowa02', 'University of Iowa')));
    $this->assertFalse(FleetDomains::isReportable($this->application('uihc01', FleetDomains::EXCLUDED_ORGANIZATION)));
  }

  /**
   * Only reportable applications' names are offered for --apps validation.
   *
   * An out-of-scope name must not validate: iteration would skip it and the
   * report would come back empty rather than saying the name was rejected.
   */
  public function testReportableAppNames(): void {
    $applications = [
      $this->application('uiowa02', 'University of Iowa'),
      $this->application('uihc01', FleetDomains::EXCLUDED_ORGANIZATION),
      $this->application('uiowa09', 'University of Iowa'),
    ];

    $this->assertSame(['uiowa02', 'uiowa09'], FleetDomains::reportableAppNames($applications));
    $this->assertSame([], FleetDomains::reportableAppNames([]));
  }

  /**
   * An ApplicationResponse-shaped object for the static helpers.
   */
  private function application(string $name, string $organization): object {
    return (object) [
      'hosting' => (object) ['id' => "prod:{$name}"],
      'organization' => (object) ['name' => $organization],
    ];
  }

}
