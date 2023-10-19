<?php

namespace Drupal\Tests\uiowa_search\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the JavaScript functionality of the uiowa_search module.
 *
 * @group uiowa_search
 */
class SearchTest extends WebDriverTestBase {
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'uids_base';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'uiowa_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('uiowasearch', [
      'region' => 'search',
      'id' => 'uiowasearch',
      'plugin' => 'uiowa_search_form',
    ]);
  }

  /**
   * Test that the search input is visible after clicking toggle button.
   */
  public function testSearchInputVisibleAfterClickingSearchButton(): void {
    $this->config('uids_base.settings')->set('header.type', 'inline')->save();
    $this->drupalGet('<front>');
    $page = $this->getSession()->getPage();
    $button = $page->findButton('Search');
    $this->assertNotEmpty($button);
    $button->click();
    $field = $page->findField('edit-search-terms');
    $this->assertTrue($field->isVisible());
  }

}
