<?php

namespace Drupal\Tests\sitenow\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\sitenow\ConfigOverride\GoogleTagOverride;

/**
 * Tests Google Tag container overrides.
 *
 * Tests that container status is disabled on non-production environments
 * and when uiowa_core.gtag is disabled.
 *
 * @group unit
 */
class GoogleTagOverrideTest extends UnitTestCase {

  /**
   * Unset the environment variable after each test.
   */
  public function tearDown(): void {
    parent::tearDown();
    putenv('AH_SITE_ENVIRONMENT');
  }

  /**
   * Builds a mock config factory with the given uiowa_core.gtag value.
   *
   * @param int $gtag
   *   The uiowa_core.gtag config value.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   A mock config factory.
   */
  protected function getConfigFactory(int $gtag): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('uiowa_core.gtag')
      ->willReturn($gtag);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('uiowa_core.settings')
      ->willReturn($config);

    return $config_factory;
  }

  /**
   * Tests that tag_container_ids are not overridden in prod with gtag on.
   */
  public function testContainersNotOverriddenInProd() {
    putenv('AH_SITE_ENVIRONMENT=prod');
    $override = new GoogleTagOverride($this->getConfigFactory(1));
    $overrides = $override->loadOverrides(['google_tag.container.G-TEST.123']);
    $this->assertEmpty($overrides);
  }

  /**
   * Tests that status is overridden to FALSE on non-prod.
   *
   * @dataProvider nonProdEnvProvider
   */
  public function testContainersOverriddenInNonProd($env) {

    if ($env) {
      putenv("AH_SITE_ENVIRONMENT={$env}");
    }

    $override = new GoogleTagOverride($this->getConfigFactory(1));
    $overrides = $override->loadOverrides(['google_tag.container.G-TEST.123']);
    $this->assertFalse($overrides['google_tag.container.G-TEST.123']['status']);
  }

  /**
   * Tests that status is overridden to FALSE when gtag is disabled.
   */
  public function testContainersOverriddenWhenGtagDisabled() {
    putenv('AH_SITE_ENVIRONMENT=prod');
    $override = new GoogleTagOverride($this->getConfigFactory(0));
    $overrides = $override->loadOverrides(['google_tag.container.G-TEST.123']);
    $this->assertFalse($overrides['google_tag.container.G-TEST.123']['status']);
  }

  /**
   * Tests that non-container config names are not touched.
   */
  public function testNonContainerConfigNotOverridden() {
    putenv('AH_SITE_ENVIRONMENT=dev');
    $override = new GoogleTagOverride($this->getConfigFactory(1));
    $overrides = $override->loadOverrides(['google_tag.settings']);
    $this->assertArrayNotHasKey('google_tag.settings', $overrides);
  }

  /**
   * Return environment strings for AH_SITE_ENVIRONMENT and FALSE for local.
   *
   * @return array
   *   Array of environment strings.
   */
  public function nonProdEnvProvider() {
    return [
      [FALSE],
      ['dev'],
      ['test'],
      ['stage'],
    ];
  }

}
