<?php

namespace Drupal\Tests\sitenow_landing_pages\Functional;

/**
 * Tests landing pages on a site with a parent organization.
 *
 * @group sitenow_landing_pages
 */
class LandingPageParentSiteTest extends LandingPageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Provides the parent organization schema for system.site.
    'sitenow_landing_pages_test',
  ];

  /**
   * Tests that landing pages render as if the site has no parent site.
   */
  public function testParentSite(): void {
    $this->config('system.site')
      ->set('has_parent', TRUE)
      ->set('parent.name', 'Parent Organization')
      ->set('parent.url', 'https://example.com')
      ->save();
    $assert = $this->assertSession();

    // A parent site forces the 'below' header on regular pages.
    $this->drupalGet($this->page->toUrl());
    $assert->elementTextContains('css', 'header .parent-site-name', 'Parent Organization');
    $assert->elementExists('css', '.iowa-bar--narrow');

    $this->drupalGet($this->landingPage->toUrl());
    $assert->pageTextNotContains('Parent Organization');
    $assert->elementNotExists('css', '.iowa-bar--narrow');
    $assert->elementExists('css', 'body.no-top-links');
  }

}
