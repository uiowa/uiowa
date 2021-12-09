<?php

namespace Drupal\Tests\sitenow_find_text\Functional;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * AJAX-based tests for the SiteNow Find Text module.
 *
 * @group sitenow_find_text
 */
class FindTextAjaxTest extends WebDriverTestBase {

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
    $this->user = $this->drupalCreateUser(['access find text']);
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

    // Fetch the Find Text page.
    $this->drupalGet($find_text_page);
    // Check that it loaded and we had access by looking for
    // its title.
    $this->assertSession()->pageTextContains('Find Text');
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
    $this->assertTrue($this->assertSession()->waitForText('No results found.', 1000));
  }

}
