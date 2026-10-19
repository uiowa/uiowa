<?php

namespace Drupal\Tests\sitenow_landing_pages\Functional;

use Drupal\Component\Uuid\Php as Uuid;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\simple_sitemap\Queue\QueueWorker;

/**
 * Tests what a page changes when it is designated as a landing page.
 *
 * @group sitenow_landing_pages
 */
class LandingPageTest extends LandingPageTestBase {

  /**
   * Tests that regular pages are unchanged.
   */
  public function testRegularPage(): void {
    $this->drupalGet($this->page->toUrl());
    $assert = $this->assertSession();

    $assert->elementNotExists('css', 'meta[name="robots"][content*="noindex"]');
    foreach (self::REGIONS as $region) {
      $assert->elementExists('css', '#block-powered-' . str_replace('_', '-', $region));
    }
    $assert->elementExists('css', '#block-uids-base-toplinks');
    $assert->elementExists('css', '#block-alertsblock');
    $assert->elementExists('css', 'nav.nav--horizontal');
    $assert->elementExists('css', 'header .site-name');
    $assert->elementExists('css', 'body.has-top-links');
  }

  /**
   * Tests what a landing page hides.
   */
  public function testLandingPage(): void {
    $this->drupalGet($this->landingPage->toUrl());
    $assert = $this->assertSession();

    // Search engines.
    $assert->elementAttributeContains('css', 'meta[name="robots"]', 'content', 'noindex');

    // Header.
    $assert->elementNotExists('css', '#block-powered-search');
    $assert->elementNotExists('css', '#block-powered-primary-menu');
    $assert->elementNotExists('css', 'nav.nav--horizontal');
    $assert->elementNotExists('css', '#block-uids-base-toplinks');
    $assert->elementNotExists('css', '#block-alertsblock');
    $assert->elementNotExists('css', '#block-powered-breadcrumb');
    $assert->elementNotExists('css', 'header .site-name');
    $assert->elementExists('css', 'body.no-top-links.inline');

    // Footer: only the socket remains.
    $assert->elementNotExists('css', '#block-powered-footer-first');
    $assert->elementNotExists('css', '#block-powered-footer-second');
    $assert->elementNotExists('css', '.footer__container--flex');
    $assert->elementExists('css', '.footer .socket');
  }

  /**
   * Tests that a landing page can opt back into the site name.
   */
  public function testLandingPageSiteName(): void {
    $this->landingPage->set('field_landing_page_site_name', TRUE)->save();
    $this->drupalGet($this->landingPage->toUrl());

    $this->assertSession()->elementTextContains('css', 'header .site-name', 'Test Site Name');
  }

  /**
   * Tests that a hidden site name switches a 'below' header to 'inline'.
   */
  public function testBelowHeader(): void {
    $this->config('uids_base.settings')->set('header.type', 'below')->save();

    $this->drupalGet($this->landingPage->toUrl());
    $this->assertSession()->elementExists('css', 'body.inline');
    $this->assertSession()->elementNotExists('css', 'body.below');
    $this->assertSession()->elementNotExists('css', '.iowa-bar--narrow');

    // With the site name shown, the site's header type applies again.
    $this->landingPage->set('field_landing_page_site_name', TRUE)->save();
    $this->drupalGet($this->landingPage->toUrl());
    $this->assertSession()->elementExists('css', 'body.below');
    $this->assertSession()->elementExists('css', '.iowa-bar--narrow');
  }

  /**
   * Tests the site name checkbox on the page form.
   */
  public function testSiteNameCheckbox(): void {
    $this->drupalLogin($this->drupalCreateUser(['create page content']));
    $assert = $this->assertSession();

    // It is only visible while the landing page checkbox is checked.
    $this->drupalGet('node/add/page');
    $assert->fieldExists('field_landing_page_site_name[value]');
    $assert->elementAttributeContains('css', '.field--name-field-landing-page-site-name', 'data-drupal-states', 'field_landing_page[value]');

    // Sites without a site name don't offer it.
    $this->config('system.site')->set('name', '')->save();
    $this->drupalGet('node/add/page');
    $assert->fieldExists('field_landing_page[value]');
    $assert->fieldNotExists('field_landing_page_site_name[value]');
  }

  /**
   * Tests that the checkboxes join the page options when they exist.
   */
  public function testPageOptions(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_publish_options',
      'entity_type' => 'node',
      'type' => 'list_string',
      'cardinality' => -1,
      'settings' => [
        'allowed_values' => ['title_hidden' => 'Visually hide title'],
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_publish_options',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Page Options',
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'page')
      ->setComponent('field_publish_options', ['type' => 'options_buttons'])
      ->save();

    $this->drupalLogin($this->drupalCreateUser(['create page content']));
    $this->drupalGet('node/add/page');

    $options = $this->assertSession()->elementExists('css', '.field--name-field-publish-options');
    $this->assertNotNull($options->findField('field_landing_page[value]'));
    $this->assertNotNull($options->findField('field_landing_page_site_name[value]'));
    $this->assertNotNull($options->findField('field_publish_options[title_hidden]'));
  }

  /**
   * Tests the landing page changes that involve Layout Builder.
   */
  public function testLayoutBuilder(): void {
    $display = LayoutBuilderEntityViewDisplay::load('node.page.default');
    $display->enableLayoutBuilder()->setOverridable()->save();

    // Reload the pages so they have the new layout field.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    $this->page = $storage->load($this->page->id());
    $this->landingPage = $storage->load($this->landingPage->id());

    // Breadcrumbs placed in a layout are hidden on landing pages.
    foreach ([$this->page, $this->landingPage] as $node) {
      $component = new SectionComponent((new Uuid())->generate(), 'content', [
        'id' => 'system_breadcrumb_block',
        'label_display' => FALSE,
      ]);
      $node->get('layout_builder__layout')->appendSection(new Section('layout_onecol', [], [$component]));
      $node->save();
    }

    $assert = $this->assertSession();
    $this->drupalGet($this->page->toUrl());
    $assert->elementExists('css', 'main nav.breadcrumb, main [aria-labelledby="system-breadcrumb"]');
    $this->drupalGet($this->landingPage->toUrl());
    $assert->elementNotExists('css', 'main nav.breadcrumb, main [aria-labelledby="system-breadcrumb"]');

    // Landing page layouts can't add the main navigation block.
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'configure all page node layout overrides',
    ]));
    $this->drupalGet('layout_builder/choose/block/overrides/node.' . $this->page->id() . '/0/content');
    $assert->linkByHrefExists('menu_block%3Amain');
    $this->drupalGet('layout_builder/choose/block/overrides/node.' . $this->landingPage->id() . '/0/content');
    $assert->linkByHrefNotExists('menu_block%3Amain');
  }

  /**
   * Tests that landing pages are left out of the sitemap.
   */
  public function testSitemap(): void {
    /** @var \Drupal\simple_sitemap\Manager\Generator $generator */
    $generator = \Drupal::service('simple_sitemap.generator');
    $generator->entityManager()->setBundleSettings('node', 'page', ['index' => TRUE]);
    $generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet('sitemap.xml');
    $assert = $this->assertSession();
    $assert->responseContains($this->page->toUrl()->setAbsolute()->toString());
    $assert->responseNotContains($this->landingPage->toUrl()->setAbsolute()->toString());
  }

}
