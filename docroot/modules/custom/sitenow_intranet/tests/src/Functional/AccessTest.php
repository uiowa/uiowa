<?php

namespace Drupal\Tests\sitenow_intranet\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group sitenow_intranet
 */
class AccessTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'uids_base';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'config_split',
    'node',
    'restrict_ip',
    'robotstxt',
    'samlauth',
    'sitenow_intranet',
    'simple_sitemap',
    'uiowa_auth',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Set uids_base header type to avoid Twig error. There is some additional
    // setup happening in sitenow_intranet.install.
    $this->config('uids_base.settings')->set('header.type', 'inline')->save();
  }

  /**
   * Test hook_restrict_ip_access_denied_page_alter in sitenow_intranet.
   */
  public function testNodeReturnsAccessDenied() {
    $node = $this->drupalCreateNode();
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * Test sitemap.xml returns access denied.
   */
  public function testSitemapReturnsAccessDenied() {
    $this->drupalGet('sitemap.xml');
    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * Test robots.txt returns access denied.
   */
  public function testRobotsReturnsAccessDenied() {
    $this->drupalGet('robots.txt');
    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * Test the footer login link is not present.
   */
  public function testNoFooterLoginLink() {
    $node = $this->drupalCreateNode();
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->elementNotExists('css', '.uiowa-footer--login-link');
  }

}
