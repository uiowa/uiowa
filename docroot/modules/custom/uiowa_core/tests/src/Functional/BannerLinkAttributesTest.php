<?php

namespace Drupal\Tests\uiowa_core\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests banner link attribute rendering.
 *
 * @group uiowa_core
 */
class BannerLinkAttributesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'uids_base';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'layout_builder_custom',
    'uiowa_core',
    'uiowa_core_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set uids_base header type to avoid Twig error.
    $this->config('uids_base.settings')->set('header.type', 'inline')->save();
  }

  /**
   * Tests analytics attributes on rendered banner links.
   */
  public function testBannerLinkAnalyticsAttributesRendering(): void {
    // Title-linked banner should render analytics attributes on headline link.
    $this->drupalGet('/banner_test_element', [
      'query' => [
        'title' => 1,
        'event' => 'Primary CTA',
      ],
    ]);

    $headline_link = $this->headlineLink();
    $this->assertSame('primary_cta', $headline_link->getAttribute('data-sn-event'));
    $this->assertSame('click', $headline_link->getAttribute('data-sn-event-type'));
    $this->assertSame('button', $headline_link->getAttribute('data-sn-event-component'));
    $this->assertSame('Apply now', $headline_link->getAttribute('data-sn-event-label'));

    // Button-linked banner should render analytics attributes on button link.
    // An easy way to replicate this with a single link
    // is to simply not include the title.
    $this->drupalGet('/banner_test_element', [
      'query' => [
        'event' => 'Secondary CTA',
      ],
    ]);

    $button_link = $this->buttonLink();
    $this->assertSame('secondary_cta', $button_link->getAttribute('data-sn-event'));
    $this->assertSame('click', $button_link->getAttribute('data-sn-event-type'));
    $this->assertSame('button', $button_link->getAttribute('data-sn-event-component'));
    $this->assertSame('Apply now', $button_link->getAttribute('data-sn-event-label'));

    // When event name is missing, no analytics attributes should render.
    $this->drupalGet('/banner_test_element');

    $button_link = $this->buttonLink();
    $this->assertNull($button_link->getAttribute('data-sn-event'));
    $this->assertNull($button_link->getAttribute('data-sn-event-type'));
    $this->assertNull($button_link->getAttribute('data-sn-event-component'));
    $this->assertNull($button_link->getAttribute('data-sn-event-label'));
  }

  /**
   * Gets headline link for title-linked banner.
   */
  protected function headlineLink(): NodeElement {
    return $this->assertSession()->elementExists('css', '.banner__content a.click-target');
  }

  /**
   * Gets button link for button-linked banner.
   */
  protected function buttonLink(): NodeElement {
    return $this->assertSession()->elementExists('css', '.banner__content .banner__action a.bttn');
  }

}
