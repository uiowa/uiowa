<?php

namespace Drupal\Tests\uiowa_search\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the site search results page.
 *
 * @group uiowa_search
 */
class SearchResultsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'uids_base';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'uiowa_search',
  ];

  /**
   * Tests that the search term is passed to the all-University search link.
   */
  public function testSearchTermsArePassedToSearchAllLink(): void {
    $this->config('uids_base.settings')->set('header.type', 'inline')->save();
    $this->config('uiowa_search.settings')
      ->set('uiowa_search.display_search_all_uiowa', TRUE)
      ->set('uiowa_search.cse_engine_id', 'test-engine')
      ->set('uiowa_search.cse_scope', 'test-scope')
      ->save();

    $terms = 'Hawk Test';
    $this->drupalGet('search', ['query' => ['terms' => $terms]]);
    $this->assertSession()->statusCodeEquals(200);
    $link = $this->getSession()->getPage()
      ->findLink("Search all University of Iowa for $terms");

    $this->assertNotNull($link);
    parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
    $this->assertSame($terms, $query['terms']);
  }

}
