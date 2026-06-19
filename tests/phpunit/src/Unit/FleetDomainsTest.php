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

}
