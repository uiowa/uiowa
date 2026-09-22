<?php

namespace Drupal\Tests\uids_base\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests UIDS Base theme settings used during page rendering.
 *
 * @group uids_base
 */
class ThemeSettingsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'uids_base';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * Tests page classes supplied by the configured theme settings.
   */
  public function testPageClassesFromThemeSettings(): void {
    $this->config('uids_base.settings')
      ->set('header.type', 'inline')
      ->set('header.nav_style', 'toggle')
      ->set('header.sticky', TRUE)
      ->set('header.toppage', TRUE)
      ->set('style.style_selector', 'gray')
      ->set('fonts.font-family', 'serif')
      ->save();

    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->elementExists('css', 'body.header-sticky');
    $session->elementExists('css', 'body.top-scroll');
    $session->elementExists('css', 'body.off-brand');
    $session->elementExists('css', 'body.text--serif');
  }

}
