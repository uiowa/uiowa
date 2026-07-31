<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Report\FleetDomains;

/**
 * Unit tests for the report commands' fleet-domain filtering rules.
 *
 * Covers the pure static helpers FleetDomains exposes for selecting
 * customer-facing domains: platform-domain exclusion and the stage→test
 * environment normalization. No Acquia API access.
 *
 * @group unit
 */
class FleetDomainsTest extends UnitTestCase {

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
   * Environment 'stage' normalizes to 'test'; everything else is unchanged.
   *
   * @dataProvider normalizeEnvProvider
   */
  public function testNormalizeEnvName(string $input, string $expected): void {
    $this->assertSame($expected, FleetDomains::normalizeEnvName($input));
  }

  /**
   * Cases for environment-name normalization.
   */
  public static function normalizeEnvProvider(): array {
    return [
      'stage maps to test' => ['stage', 'test'],
      'test unchanged' => ['test', 'test'],
      'prod unchanged' => ['prod', 'prod'],
      'dev unchanged' => ['dev', 'dev'],
    ];
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
