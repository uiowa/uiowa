<?php

namespace Drupal\Tests\uiowa_core\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use Drupal\uiowa_core\LinkAnalyticsHelper;

/**
 * Tests for LinkAnalyticsHelper.
 *
 * @group uiowa_core
 * @coversDefaultClass \Drupal\uiowa_core\LinkAnalyticsHelper
 */
class LinkAnalyticsHelperTest extends UnitTestCase {

  /**
   * @covers ::sanitizeMenuLinkAnalyticsAttributes
   */
  public function testMenuLinkSetsComponentAndFallbackLabel(): void {
    $form_state = new FormState();
    $form_state->setValue(['link', 0, 'options', 'attributes'], [
      'data-sn-event' => 'Nav Click',
    ]);
    $form_state->setValue(['title', 0, 'value'], 'About Us');

    LinkAnalyticsHelper::sanitizeMenuLinkAnalyticsAttributes($form_state, 'menu-link');

    $attrs = $form_state->getValue(['link', 0, 'options', 'attributes']);
    $this->assertEquals('nav_click', $attrs['data-sn-event']);
    $this->assertEquals('menu-link', $attrs['data-sn-event-component']);
    $this->assertEquals('About Us', $attrs['data-sn-event-label']);
  }

}
