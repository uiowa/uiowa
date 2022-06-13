<?php

namespace Drupal\Tests\sitenow_find_text\Functional;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\menu_admin_per_menu\Traits\MenuLinkContentTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * AJAX-based tests for the SiteNow Find Text module.
 *
 * @group sitenow_find_text
 */
class FindTextAjaxTest extends WebDriverTestBase {
  use NodeCreationTrait;
  use MenuLinkContentTrait;
  use TaxonomyTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'sitenow_find_text',
    'menu_link_content',
    'node',
    'taxonomy',
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
   * Test searching menu items.
   */
  public function testMenuFind() {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link */
    $menu = $this->createMenuContentLink();
    $menu_title = $menu->getTitle();
    $menu_id = $menu->id();
    $menu_uri = $menu->link->getValue()[0]['uri'];
    // Some random-generated menu uris won't fully render,
    // such as "route:<front>", as the bracketed content will be converted to
    // "<front></front>". While this isn't ideal, it's expected,
    // and we can still test the rest of the functionality.
    $menu_uri = preg_replace('|<.*?>|', '', $menu_uri);
    // Create a second menu item to test that not ALL menu items
    // come up for all search terms, and to test regexp searches.
    $second_menu = $this->createMenuContentLink([
      'link' => [
        'uri' => 'https://prod.drupal.tester.edu',
      ],
    ]);
    $second_menu_title = $second_menu->getTitle();
    $second_menu_id = $second_menu->id();
    $second_menu_uri = $second_menu->link->getValue()[0]['uri'];

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
    // because we checked for the menu title. Wait for
    // the "Menu: #" result.
    $this->assertTrue($session->waitForText('Menu: ' . $menu_id, 1000));
    // Check that we got the right menu element.
    $session->pageTextMatchesCount(1, '%Menu: ' . $menu_id . '%');
    // Check that we matched and labelled it as a title.
    $session->pageTextMatchesCount(1, '%Title ' . $menu_title . '%');

    // And now let's do a search for the uri.
    $this->submitForm([
      'needle' => $menu_uri,
      'render' => TRUE,
      'regexed' => FALSE,
    ],
      'search');
    // We shouldn't get the "no results" response,
    // because we checked for the menu title. Wait for
    // the "Menu: #" result.
    $this->assertTrue($session->waitForText('Menu: ' . $menu_id, 1000));
    // Check that we got the right menu element.
    $session->pageTextMatchesCount(1, '%Menu: ' . $menu_id . '%');
    // Check that we matched and labelled it as a uri.
    $session->pageTextMatchesCount(1, '%Link Uri ' . $menu_uri . '%');

    // And now let's do a regex search for the titles.
    $this->submitForm([
      'needle' => '(' . $menu_title . ')|(' . $second_menu_title . ')',
      'render' => TRUE,
      'regexed' => TRUE,
    ],
      'search');
    // We shouldn't get the "no results" response,
    // because we checked for the menu title. Wait for
    // the "Menu: #" result.
    $this->assertTrue($session->waitForText('Menu: ' . $menu_id, 1000));
    // Check that we got the right menu elements, and only one result
    // for each of the two items.
    $session->pageTextMatchesCount(1, '%Menu: ' . $menu_id . '%');
    $session->pageTextMatchesCount(1, '%Menu: ' . $second_menu_id . '%');
    $session->pageTextMatchesCount(1, '%Title ' . $menu_title . '%');
    $session->pageTextMatchesCount(1, '%Title ' . $second_menu_title . '%');

    // And now let's do a search for the uri using regexp.
    // We should only get results matching the second menu item.
    $this->submitForm([
      'needle' => $second_menu_uri,
      'render' => TRUE,
      'regexed' => TRUE,
    ],
      'search');
    $this->assertTrue($session->waitForText('Menu: ' . $second_menu_id, 1000));
    $session->pageTextMatchesCount(0, '%Menu: ' . $menu_id . '%');
    $session->pageTextMatchesCount(1, '%Menu: ' . $second_menu_id . '%');
    $session->pageTextMatchesCount(0, '%Link Uri ' . $menu_uri . '%');
    $session->pageTextMatchesCount(1, '%Link Uri ' . $second_menu_uri . '%');
  }

  /**
   * Test searching node fields.
   */
  public function testNodeFind() {
    $node = $this->createNode();
    $node_title = $node->getTitle();
    $node_id = $node->id();
    // @todo Search for the body text. This doesn't seem
    //   to be stored in the testing database? Unsure of
    //   where it goes, and how to connect it to the
    //   node__body table that gets searched.
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
    // because we checked for the menu title. Wait for
    // the "Node: #" result.
    $this->assertTrue($session->waitForText('Node: ' . $node_id, 1000));
    // Check that we matched and labelled it as a title.
    $session->pageTextContains('Title ' . $node_title);
  }

  /**
   * Test searching taxonomy fields.
   */
  public function testTaxonomyFind() {
    $vocab = $this->createVocabulary();
    $term = $this->createTerm($vocab);
    $term_id = $term->id();
    $term_name = $term->getName();
    $term_description = $term->getDescription();

    // Login.
    $this->drupalLogin($this->user);
    // Create a session.
    $session = $this->assertSession();
    // Fetch the Find Text page.
    $this->drupalGet($this->findTextPage);

    // Fill out and submit a search. We don't have any content,
    // so we should end up with a "no results" response table.
    $this->submitForm([
      'needle' => $term_name,
      'render' => TRUE,
      'regexed' => FALSE,
    ],
      'search');
    // We shouldn't get the "no results" response,
    // because we checked for the menu title. Wait for
    // the "Term: #" result.
    $this->assertTrue($session->waitForText('Term: ' . $term_id, 1000));
    // Check that we matched and labelled it as a title.
    $session->pageTextContains('Name ' . $term_name);

    // Fill out and submit a search. We don't have any content,
    // so we should end up with a "no results" response table.
    $this->submitForm([
      'needle' => $term_description,
      'render' => TRUE,
      'regexed' => FALSE,
    ],
      'search');
    // We shouldn't get the "no results" response,
    // because we checked for the menu title. Wait for
    // the "Term: #" result.
    $this->assertTrue($session->waitForText('Term: ' . $term_id, 1000));
    // Check that we matched and labelled it as a title.
    $session->pageTextContains('Description ' . $term_description);
  }

}
