<?php

namespace Drupal\Tests\sitenow_find_text\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the SiteNow Find Text module.
 *
 * @group sitenow_find_text
 */
class FindTextFunctionalTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'sitenow_find_text',
  ];

  /**
   * A simple user.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function setUp() : void {
    parent::setUp();
    // Create a user with the find text permission.
    $this->user = $this->drupalCreateUser(['administer sitenow_find_text configuration']);
  }

  /**
   * Tests that the Find Text page can be reached by the webmaster role.
   */
  public function testPageExists() {
    // Login.
    $this->drupalLogin($this->user);

    // Fetch the Find Text page, and check if we have access
    // as a user with the 'webmaster' role.
    $this->drupalGet('admin/find-text');
    $this->assertSession()->statusCodeEquals(200);
  }
}
