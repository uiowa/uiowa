<?php

namespace Drupal\Tests\sitenow\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\sitenow\ConfigOverride\GoogleAnalyticsOverride;

/**
 * Tests Google Analytics is not tracking on non-production environments.
 *
 * We have to override the configuration like this instead of disabling the
 * Google Analytics module because it is listed in Config Ignore.
 *
 * @group unit
 */
class GoogleAnalyticsOverrideTest extends UnitTestCase {

  /**
   * Unset the environment variable after each test.
   */
  public function tearDown(): void {
    parent::tearDown();
    putenv('AH_SITE_ENVIRONMENT');
  }

  /**
   * Test GA account is not overridden in prod environment.
   */
  public function testConfigNotOverriddenInProd() {
    putenv('AH_SITE_ENVIRONMENT=prod');

    // Create a mock for ConfigFactoryInterface.
    $config_factory_mock = $this->createMock(ConfigFactoryInterface::class);

    // Instantiate GoogleAnalyticsOverride with the mock config factory.
    $override = new GoogleAnalyticsOverride($config_factory_mock);
    $overrides = $override->loadOverrides(['google_analytics.settings']);
    $this->assertArrayNotHasKey('google_analytics.settings', $overrides);
  }

  /**
   * Tests GA account is overridden in non-prod environments.
   *
   * @dataProvider envProvider
   */
  public function testConfigSetToEmptyStringInNonProd($env) {
    if ($env) {
      putenv("AH_SITE_ENVIRONMENT={$env}");
    }

    // Create a mock for ConfigFactoryInterface.
    $config_factory_mock = $this->createMock(ConfigFactoryInterface::class);

    // Instantiate GoogleAnalyticsOverride with the mock config factory.
    $override = new GoogleAnalyticsOverride($config_factory_mock);
    $overrides = $override->loadOverrides(['google_analytics.settings']);
    $this->assertEquals('', $overrides['google_analytics.settings']['account']);
  }

  /**
   * Return environment strings for AH_SITE_ENVIRONMENT and FALSE for local.
   *
   * @return array
   *   Array of environment strings.
   */
  public function envProvider() {
    return [
      [FALSE],
      ['dev'],
      ['test'],
    ];
  }

}
