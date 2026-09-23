<?php

namespace Drupal\Tests\uids_base\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;

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
    'block',
    'menu_link_content',
    'node',
    'superfish',
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

  /**
   * Tests that the toggle nav renders its drawer when the menu has links.
   */
  public function testToggleNavRendersMenuLinks(): void {
    $this->setToggleNav();
    MenuLinkContent::create([
      'title' => 'Toggle nav test link',
      'link' => ['uri' => 'internal:/node'],
      'menu_name' => 'main',
    ])->save();

    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->elementExists('css', 'button.toggle-nav__bttn');
    $session->elementTextContains('css', '.o-canvas__drawer', 'Toggle nav test link');
  }

  /**
   * Tests that the toggle nav is omitted when the menu has no links.
   */
  public function testToggleNavOmittedForEmptyMenu(): void {
    $this->setToggleNav();

    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->elementNotExists('css', 'button.toggle-nav__bttn');
    $session->elementNotExists('css', '.o-canvas__drawer');
  }

  /**
   * Configures the inline header with toggle navigation.
   */
  protected function setToggleNav(): void {
    $this->config('uids_base.settings')
      ->set('header.type', 'inline')
      ->set('header.nav_style', 'toggle')
      ->save();
  }

}
