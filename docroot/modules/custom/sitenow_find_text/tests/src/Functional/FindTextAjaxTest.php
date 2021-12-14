<?php

namespace Drupal\Tests\sitenow_find_text\Functional;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\menu_admin_per_menu\Traits\MenuLinkContentTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * AJAX-based tests for the SiteNow Find Text module.
 *
 * @group sitenow_find_text
 */
class FindTextAjaxTest extends WebDriverTestBase {
  use NodeCreationTrait;
  use MenuLinkContentTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'sitenow_find_text',
    'menu_link_content',
    'node',
  ];


  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A simple user.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * The Find Text tool page.
   */
  private $find_text_page;

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function setUp() : void {
    parent::setUp();
    // Create a user with the find text permission.
    $this->user = $this->drupalCreateUser(['access find text']);
    // Grab the relative path to our find text page.
    $this->find_text_page = Url::fromRoute('sitenow_find_text.search_form', [], ['absolute' => FALSE])
      ->toString();
  }

  /**
   * Test that the functions run and return no results text.
   */
  public function testNoResults() {
    // Login.
    $this->drupalLogin($this->user);

    // Create a session.
    $session = $this->assertSession();

    // Fetch the Find Text page.
    $this->drupalGet($this->find_text_page);
    // Check that it loaded and we had access by looking for
    // its title.
    $session->pageTextContains('Find Text');
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
    $this->assertTrue($session->waitForText('No results found.', 1000));
  }


  /**
   * Method for creating a menu link.
   */
  public function testMenuLinkFind() {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link */
    $menu_link = $this->createMenuContentLink();
    $menu_title = $menu_link->getTitle();
    $menu_uri = $menu_link->link->getValue()[0]['uri'];
    $menu_id = $menu_link->id();

    // Login.
    $this->drupalLogin($this->user);

    // Create a session.
    $session = $this->assertSession();

    // Fetch the Find Text page.
    $this->drupalGet($this->find_text_page);

    // Fill out and submit a search. We don't have any content,
    // so we should end up with a "no results" response table.
    $this->submitForm([
      'needle' => $menu_title,
      'render' => TRUE,
      'regexed' => FALSE,
    ],
      'search');
    // We shouldn't get the "no results" response, because we checked for the menu title.
    $this->assertFalse($session->waitForText('No results found.', 1000));
    // Check that we got the right menu element.
    $session->pageTextContains('Menu: ' . $menu_id);
    // Check that we matched and labelled it as a title.
    $session->pageTextContains('Title ' . $menu_title);

    // And now let's do a search for the uri.
    $this->submitForm([
      'needle' => $menu_uri,
      'render' => TRUE,
      'regexed' => FALSE,
    ],
      'search');
    // We shouldn't get the "no results" response, because we checked for the menu uri.
    $this->assertFalse($session->waitForText('No results found.', 1000));
    // Check that we got the right menu element.
    $session->pageTextContains('Menu: ' . $menu_id);
    // Check that we matched and labelled it as a uri.
    $session->pageTextContains('Link Uri ' . $menu_uri);
  }

}
