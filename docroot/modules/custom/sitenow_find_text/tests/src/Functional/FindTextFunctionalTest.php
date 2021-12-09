<?php

namespace Drupal\Tests\sitenow_find_text\Functional;

use Drupal\Core\Url;
use Drupal\Core\Config\FileStorage;
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
   * A simple authenticated user.
   *
   * @var \Drupal\user\Entity\User
   */
  private $authUser;

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
    $this->user = $this->drupalCreateUser(['access find text']);
    $default_auth_perms = user_role_permissions(['authenticated'])['authenticated'];
    $this->authUser = $this->drupalCreateUser($default_auth_perms);
  }

  /**
   * Tests that the Find Text page can be reached by the specified perm.
   */
  public function testPageAccess() {
    // Login.
    $this->drupalLogin($this->user);

    // Grab the relative path to our find text page.
    $find_text_page = Url::fromRoute('sitenow_find_text.search_form', [], ['absolute' => FALSE])
      ->toString();

    // Fetch the Find Text page, and check if we have access
    // as a user with the 'webmaster' role.
    $this->drupalGet($find_text_page);
    $this->assertSession()->statusCodeEquals(200);

    // Logout and repeat the as anonymous user. We shouldn't
    // have access anymore.
    $this->drupalLogout();
    $this->drupalGet($find_text_page);
    $this->assertSession()->statusCodeEquals(403);

    // Now check that a basic authenticated user does not have access.
    // The page should exist, but we shouldn't have access.
    $this->drupalLogin($this->authUser);
    $this->drupalGet($find_text_page);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test that the functions run and return no results text.
   */
  public function testNoResults() {
    // Login.
    $this->drupalLogin($this->user);
    // Grab the relative path to our find text page.
    $find_text_page = Url::fromRoute('sitenow_find_text.search_form', [], ['absolute' => FALSE])
      ->toString();

    // Fetch the Find Text page, and check if we have access
    // as a user with the 'webmaster' role.
    $this->drupalGet($find_text_page);
    $this->assertSession()->statusCodeEquals(200);
    // Fill out and submit a search. We don't have any content,
    // so we should end up with a "no results" response table.
    $this->submitForm([
      'needle' => 'Test search string',
      'render' => TRUE,
      'regexed' => FALSE,
    ],
    'search');
    // Check that we have the no results display. If it shows the text,
    // then the process completed successfully with no found results.
    // If it doesn't display, there was an error somewhere.
    $this->assertSession()->pageTextContains('No results found.');
  }
}
