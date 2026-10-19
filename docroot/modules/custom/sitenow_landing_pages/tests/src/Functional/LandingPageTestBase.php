<?php

namespace Drupal\Tests\sitenow_landing_pages\Functional;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\NodeInterface;
use Drupal\system\Entity\Menu;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\sitenow_landing_pages\Traits\LandingPageFieldsTrait;

/**
 * Sets up regular and landing pages with blocks in every affected region.
 */
abstract class LandingPageTestBase extends BrowserTestBase {

  use LandingPageFieldsTrait;

  /**
   * Regions landing pages hide, each given a block in setUp().
   */
  protected const REGIONS = [
    'search',
    'primary_menu',
    'breadcrumb',
    'footer_first',
    'footer_second',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'uids_base';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'allowed_formats',
    'block',
    'field',
    'fontawesome',
    'layout_builder',
    'menu_block',
    'menu_link_content',
    'metatag',
    'node',
    'options',
    'simple_sitemap',
    'sitenow_landing_pages',
    'uiowa_alerts',
  ];

  /**
   * A regular page.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $page;

  /**
   * A page designated as a landing page.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $landingPage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('uids_base.settings')
      ->set('header.type', 'inline')
      ->set('header.nav_style', 'horizontal')
      ->save();
    $this->config('system.site')->set('name', 'Test Site Name')->save();

    $this->drupalCreateContentType(['type' => 'page']);
    $this->createLandingPageFields();

    // Top links, with the block ID the theme looks for.
    Menu::create(['id' => 'top-links', 'label' => 'Top links'])->save();
    MenuLinkContent::create([
      'title' => 'Top link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'top-links',
    ])->save();
    $this->drupalPlaceBlock('menu_block:top-links', [
      'id' => 'uids_base_toplinks',
      'region' => 'action_menu',
    ]);

    $this->drupalPlaceBlock('uiowa_alerts_block', [
      'id' => 'alertsblock',
      'region' => 'alert',
    ]);

    // Blocks in the other regions landing pages hide.
    foreach (self::REGIONS as $region) {
      $this->drupalPlaceBlock('system_powered_by_block', [
        'id' => 'powered_' . $region,
        'region' => $region,
      ]);
    }

    $this->page = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Regular page',
    ]);
    $this->landingPage = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Landing page',
      'field_landing_page' => TRUE,
    ]);
  }

}
