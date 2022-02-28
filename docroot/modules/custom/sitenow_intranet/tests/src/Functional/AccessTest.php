<?php

namespace Drupal\Tests\sitenow_intranet\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for intranet module.
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
    'filter',
    'node',
    'robotstxt',
    'sitenow_intranet',
    'simple_sitemap'
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
  public function testAccessDeniedResponseCode() {
    $node = $this->drupalCreateNode();
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * Test an authenticated user gets a 403.
   */
  public function testUnauthorizedResponseCode() {
    $user = $this->createUser();
    $this->drupalLogin($user);
    $node = $this->drupalCreateNode();
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(403);
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

  /**
   * Test the title and message functionality for a 401 response.
   */
  public function testAccessDeniedTitleMessage() {
    $this->config('sitenow_intranet.settings')
      ->set('access_denied.title', 'Access Denied')
      ->set('access_denied.message', '<p>This is some markup.</p>')
      ->save();

    $this->setUpFilter();
    $node = $this->drupalCreateNode();
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextContains('Access Denied');
    $this->assertSession()->responseContains('<p>This is some markup.</p>');
  }

  /**
   * Test the title and message functionality for a 403 response.
   */
  public function testUnauthorizedTitleMessage() {
    $this->config('sitenow_intranet.settings')
      ->set('unauthorized.title', 'Unauthorized')
      ->set('unauthorized.message', '<p>This is <strong>some</strong> markup.</p>')
      ->save();

    $this->setUpFilter();
    $user = $this->createUser();
    $this->drupalLogin($user);
    $node = $this->drupalCreateNode();
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextContains('Unauthorized');
    $this->assertSession()->responseContains('<p>This is <strong>some</strong> markup.</p>');
  }

  /**
   * Set up the filter format used by our code.
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUpFilter(): void {
    $minimal = FilterFormat::create([
      'format' => 'minimal',
      'name' => 'Minimal',
      'filters' => [
        'filter_html' => [
          'status' => 1,
          'settings' => [
            'allowed_html' => '<p> <br> <strong> <a> <em>',
          ],
        ],
      ],
    ]);

    $minimal->save();
  }

}
