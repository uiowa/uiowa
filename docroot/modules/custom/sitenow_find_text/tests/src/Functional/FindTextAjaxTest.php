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
   *
   * @var \Drupal\Core\GeneratedUrl
   */
  private $findTextPage;

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function setUp() : void {
    parent::setUp();
    // Create a user with the find text permission.
    $this->user = $this->drupalCreateUser(['access find text']);
    // Grab the relative path to our find text page.
    $this->findTextPage = Url::fromRoute('sitenow_find_text.search_form', [], ['absolute' => FALSE])
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
    $this->drupalGet($this->findTextPage);
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
   * Test searching menu links.
   */
  public function testMenuLinkFind() {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link */
    $menu_link = $this->createMenuContentLink();
    $menu_title = $menu_link->getTitle();
    $menu_id = $menu_link->id();
    $menu_uri = $menu_link->link->getValue()[0]['uri'];
    // Some random-generated menu uris won't fully render,
    // such as "route:<front>", as the bracketed content will be converted to
    // "<front></front>". While this isn't ideal, it's expected,
    // and we can still test the rest of the functionality.
    $menu_uri = preg_replace('|<.*?>|', '', $menu_uri);

    // Login.
    $this->drupalLogin($this->user);
    // Create a session.
    $session = $this->assertSession();
    // Fetch the Find Text page.
    $this->drupalGet($this->findTextPage);

    // Fill out and submit a search. We don't have any content,
    // so we should end up with a "no results" response table.
    $this->submitForm([
      'needle' => $menu_title,
      'render' => TRUE,
      'regexed' => FALSE,
    ],
      'search');
    // We shouldn't get the "no results" response,
    // because we checked for the menu title.
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
    // We shouldn't get the "no results" response,
    // because we checked for the menu uri.
    $this->assertFalse($session->waitForText('No results found.', 1000));
    // Check that we got the right menu element.
    $session->pageTextContains('Menu: ' . $menu_id);
    // Check that we matched and labelled it as a uri.
    $session->pageTextContains('Link Uri ' . $menu_uri);
  }

  /**
   * Test searching node fields.
   */
  public function testNodeFind() {
    $node = $this->createNode();
    $node_title = $node->getTitle();
    $node_id = $node->id();
    // @todo Get text-based fields. Default-generated node
    //   doesn't have any, so we also need to either load config
    //   or add some fields to test.
    $field_definitions = $node->getFieldDefinitions();

    // Login.
    $this->drupalLogin($this->user);
    // Create a session.
    $session = $this->assertSession();
    // Fetch the Find Text page.
    $this->drupalGet($this->findTextPage);

    // Fill out and submit a search. We don't have any content,
    // so we should end up with a "no results" response table.
    $this->submitForm([
      'needle' => $node_title,
      'render' => TRUE,
      'regexed' => FALSE,
    ],
      'search');
    // We shouldn't get the "no results" response,
    // because we checked for the menu title.
    $this->assertFalse($session->waitForText('No results found.', 1000));
    // Check that we got the right menu element.
    $session->pageTextContains('Node: ' . $node_id);
    // Check that we matched and labelled it as a title.
    $session->pageTextContains('Title ' . $node_title);

  }

}
