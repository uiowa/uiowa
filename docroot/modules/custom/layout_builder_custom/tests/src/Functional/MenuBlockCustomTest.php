<?php

namespace Drupal\Tests\layout_builder_custom\Functional;

use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the menu_block module.
 *
 * @group menu_block
 */
class MenuBlockCustomTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The menu link plugin manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The menu link content storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuLinkContentStorage;

  /**
   * An array containing the menu link plugin ids.
   *
   * @var array
   */
  protected $links;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'layout_builder',
    'block',
    'block_content',
    'menu_block',
    'node',
    'layout_builder_styles',
    'layout_builder_custom',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->menuLinkManager = \Drupal::service('plugin.manager.menu.link');
    $this->menuLinkContentStorage = \Drupal::service('entity_type.manager')
      ->getStorage('menu_link_content');
    $this->links = $this->createLinkHierarchy();

    $this->drupalPlaceBlock('local_tasks_block');

    // Create two nodes.
    $this->createContentType([
      'type' => 'bundle_with_section_field',
      'name' => 'Bundle with section field',
    ]);

    LayoutBuilderEntityViewDisplay::load('node.bundle_with_section_field.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();
  }

  /**
   * Creates a simple hierarchy of links.
   */
  protected function createLinkHierarchy() {
    // First remove all the menu links in the menu.
    $this->menuLinkManager->deleteLinksInMenu('main');

    // Then create a simple link hierarchy:
    // - parent menu item
    //   - child-1 menu item
    //     - child-1-1 menu item
    //     - child-1-2 menu item
    //   - child-2 menu item.
    $base_options = [
      'provider' => 'menu_block',
      'menu_name' => 'main',
    ];

    $parent = $base_options + [
      'title' => 'parent menu item',
      'link' => ['uri' => 'internal:/menu-block-test/hierarchy/parent'],
    ];
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
    $link = $this->menuLinkContentStorage->create($parent);
    $link->save();
    $links['parent'] = $link->getPluginId();

    $child_1 = $base_options + [
      'title' => 'child-1 menu item',
      'link' => ['uri' => 'internal:/menu-block-test/hierarchy/parent/child-1'],
      'parent' => $links['parent'],
    ];
    $link = $this->menuLinkContentStorage->create($child_1);
    $link->save();
    $links['child-1'] = $link->getPluginId();

    $child_1_1 = $base_options + [
      'title' => 'child-1-1 menu item',
      'link' => ['uri' => 'internal:/menu-block-test/hierarchy/parent/child-1/child-1-1'],
      'parent' => $links['child-1'],
    ];
    $link = $this->menuLinkContentStorage->create($child_1_1);
    $link->save();
    $links['child-1-1'] = $link->getPluginId();

    $child_1_2 = $base_options + [
      'title' => 'child-1-2 menu item',
      'link' => ['uri' => 'internal:/menu-block-test/hierarchy/parent/child-1/child-1-2'],
      'parent' => $links['child-1'],
    ];
    $link = $this->menuLinkContentStorage->create($child_1_2);
    $link->save();
    $links['child-1-2'] = $link->getPluginId();

    $child_2 = $base_options + [
      'title' => 'child-2 menu item',
      'link' => ['uri' => 'internal:/menu-block-test/hierarchy/parent/child-2'],
      'parent' => $links['parent'],
    ];
    $link = $this->menuLinkContentStorage->create($child_2);
    $link->save();
    $links['child-2'] = $link->getPluginId();

    return $links;
  }

  /**
   * Checks that our menu block overrides are in place.
   */
  public function testMenuBlockOverrides() {
    // @todo Test menu orientation field is showing only once.
    // @todo Test parent field is hidden when follow is checked.
    // @todo Test initial visibility field is hidden when follow is checked.
    // @todo Test text 'Make sure that "Initial visibility level" is set to "1"
    //   below.' is showing.
    // @todo Test 'Visibility options' is showing and not 'Advanced'.
    // @todo Test that depth options are 1-3.
    // @todo Test that follow is set to 1 if the add block form is being used.
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $block_node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
    ]));

    // Add block to node with new style.
    $this->drupalGet('node/' . $block_node->id());
    $page->clickLink('Layout');
    $page->clickLink('Add block');
    $page->clickLink('Main navigation');
    $assert_session->elementExists('xpath', '//input[contains(@id, "edit-settings-follow")]');
    // Test label_link field is not rendered.
    $assert_session->elementNotExists('xpath', '//input[contains(@id, "edit-settings-label-link")]');
    // Test follow description is not shown.
    $assert_session->pageTextNotContains('If the active menu item is deeper than the initial visibility level set above');
    // Test that expand_all_items field is not rendered.
    $assert_session->elementNotExists('xpath', '//input[contains(@id, "edit-settings-expand-all-items")]');
    // Test that style field is not rendered.
    $assert_session->elementNotExists('xpath', '//input[contains(@id, "edit-settings-style")]');
  }

}
