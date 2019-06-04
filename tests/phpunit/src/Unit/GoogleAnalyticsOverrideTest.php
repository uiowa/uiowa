<?php

namespace Drupal\Tests\uiowa_seo\Unit\ConfigOverride;

use Drupal\Tests\UnitTestCase;
use Drupal\uiowa_seo\ConfigOverrides\GoogleAnalyticsOverride;

/**
 * Tests Google Analytics is not tracking on non-production environments.
 *
 * @group uiowa_seo.
 */
class GoogleAnalyticsOverrideTest extends UnitTestCase {

  /**
   * Tests config override for non-production environments.
   */
  public function testConfigNonProd() {
    putenv('AH_NON_PRODUCTION=1');

    $override = new GoogleAnalyticsOverride();
    $overrides = $override->loadOverrides(['google_analytics.settings']);
    $this->assertEquals('', $overrides['google_analytics.settings']['account']);
  }

}
