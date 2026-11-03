<?php

namespace Drupal\Tests\sitenow_intranet\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\sitenow_intranet\ConfigOverride\RobotstxtOverride;

/**
 * Robotstxt config override test.
 *
 * @group sitenow_intranet
 */
class RobotstxtOverrideTest extends UnitTestCase {

  /**
   * Test robots.txt denies all except the Siteimprove crawler.
   */
  public function testLoadOverrides() {
    $sut = new RobotstxtOverride();

    $overrides = $sut->loadOverrides(['robotstxt.settings']);
    $this->assertEquals("User-agent: SiteimproveBot-Crawler\r\nAllow: /\r\n\r\nUser-agent: *\r\nDisallow: /", $overrides['robotstxt.settings']['content']);
  }

  /**
   * Test that the override does not apply to other config.
   */
  public function testLoadOverridesSkipsOtherConfig() {
    $sut = new RobotstxtOverride();

    $overrides = $sut->loadOverrides(['system.site']);
    $this->assertArrayNotHasKey('robotstxt.settings', $overrides);
  }

}
