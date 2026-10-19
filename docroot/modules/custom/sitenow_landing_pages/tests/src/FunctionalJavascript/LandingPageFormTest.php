<?php

namespace Drupal\Tests\sitenow_landing_pages\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\sitenow_landing_pages\Traits\LandingPageFieldsTrait;

/**
 * Tests the landing page checkboxes on the page form.
 *
 * @group sitenow_landing_pages
 */
class LandingPageFormTest extends WebDriverTestBase {

  use LandingPageFieldsTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'sitenow_landing_pages',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.site')->set('name', 'Test Site Name')->save();
    $this->drupalCreateContentType(['type' => 'page']);
    $this->createLandingPageFields();
  }

  /**
   * Tests that the site name checkbox follows the landing page checkbox.
   */
  public function testSiteNameCheckboxVisibility(): void {
    $this->drupalLogin($this->drupalCreateUser(['create page content']));
    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();

    $site_name = $page->findField('field_landing_page_site_name[value]');
    $this->assertNotNull($site_name);
    $this->assertFalse($site_name->isVisible());

    $page->checkField('field_landing_page[value]');
    $this->assertTrue($site_name->isVisible());

    $page->uncheckField('field_landing_page[value]');
    $this->assertFalse($site_name->isVisible());
  }

}
