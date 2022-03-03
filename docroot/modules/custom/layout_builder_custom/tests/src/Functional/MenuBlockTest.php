<?php

namespace Drupal\Tests\layout_builder_custom\Functional;

use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\menu_block\Plugin\Block\MenuBlock;
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
    'node',
    'layout_builder_custom'
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

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
    ]));
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
    // @todo Test 'Visibility options' is showing and not 'Advanced'
    // @todo Test label_type field is not rendered.
    // @todo Test label_link field is not rendered.
    // @todo Test follow description is not shown.
    // @todo Test that depth options are 1-3.
    // @todo Test that expand_all_items field is not rendered.
    // @todo Test that style field is not rendered.
    // @todo Test that follow is set to 1 if the add block form is being used.
  }

  /**
   * Checks if all menu block configuration options are available.
   */
  public function testMenuBlockFormDisplay() {
    $this->drupalGet('admin/structure/block/add/menu_block:main');
    $this->assertSession()->pageTextContains('Initial visibility level');
    $this->assertSession()->pageTextContains('Number of levels to display');
    $this->assertSession()->pageTextContains('Expand all menu items');
    $this->assertSession()->pageTextContains('Fixed parent item');
    $this->assertSession()
      ->pageTextContains('Make the initial visibility level follow the active menu item.');
    $this->assertSession()->pageTextContains('Theme hook suggestion');
  }

  /**
   * Checks if all menu block settings are saved correctly.
   */
  public function testMenuBlockUi() {
    $block_id = 'main';
    $this->drupalGet('admin/structure/block/add/menu_block:main');
    $this->submitForm([
      'id' => $block_id,
      'settings[label]' => 'Main navigation',
      'settings[label_display]' => FALSE,
      'settings[level]' => 2,
      'settings[depth]' => 6,
      'settings[expand_all_items]' => TRUE,
      'settings[parent]' => 'main:',
      'settings[follow]' => TRUE,
      'settings[follow_parent]' => 'active',
      'settings[suggestion]' => 'main',
      'region' => 'primary_menu',
    ], 'Save block');

    $block = $this->blockStorage->load($block_id);
    $block_settings = $block->get('settings');
    $this->assertSame(2, $block_settings['level']);
    $this->assertSame(6, $block_settings['depth']);
    $this->assertTrue($block_settings['expand_all_items']);
    $this->assertSame('main:', $block_settings['parent']);
    $this->assertTrue($block_settings['follow']);
    $this->assertSame('active', $block_settings['follow_parent']);
    $this->assertSame('main', $block_settings['suggestion']);
  }

  /**
   * Tests the menu_block level option.
   */
  public function testMenuBlockLevel() {
    // Add new menu block.
    $block_id = 'main';
    $this->drupalGet('admin/structure/block/add/menu_block:main');
    $this->submitForm([
      'id' => $block_id,
      'settings[label]' => 'Main navigation',
      'settings[label_display]' => FALSE,
      'settings[level]' => 1,
      'region' => 'primary_menu',
    ], 'Save block');

    // Check if the parent menu item is visible, but the child menu items not.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('parent menu item');
    $this->assertSession()->pageTextNotContains('child-1 menu item');
    $this->assertSession()->pageTextNotContains('child-1-1 menu item');
    $this->assertSession()->pageTextNotContains('child-2 menu item');

    $this->drupalGet('admin/structure/block/manage/' . $block_id);
    $this->submitForm([
      'settings[level]' => 2,
    ], 'Save block');

    // Check if the menu items of level 2 are visible, but not the parent menu
    // item.
    $this->drupalGet('menu-block-test/hierarchy/parent');
    $this->assertSession()->pageTextContains('child-1 menu item');
    $this->assertSession()->pageTextContains('child-2 menu item');
    $this->assertSession()->pageTextNotContains('parent menu item');
    $this->assertSession()->pageTextNotContains('child-1-1 menu item');
  }

  /**
   * Tests the menu_block depth option.
   */
  public function testMenuBlockDepth() {
    // Add new menu block.
    $block_id = 'main';
    $this->drupalGet('admin/structure/block/add/menu_block:main');
    $this->submitForm([
      'id' => $block_id,
      'settings[label]' => 'Main navigation',
      'settings[label_display]' => FALSE,
      'settings[level]' => 1,
      'settings[depth]' => 1,
      'settings[expand_all_items]' => TRUE,
      'region' => 'primary_menu',
    ], 'Save block');

    // Check if the parent menu item is visible, but the child menu items not.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('parent menu item');
    $this->assertSession()->pageTextNotContains('child-1 menu item');
    $this->assertSession()->pageTextNotContains('child-1-1 menu item');
    $this->assertSession()->pageTextNotContains('child-2 menu item');

    $this->drupalGet('admin/structure/block/manage/' . $block_id);
    $this->submitForm([
      'settings[depth]' => 2,
    ], 'Save block');

    // Check if the menu items of level 2 are visible, but not the parent menu
    // item.
    $this->drupalGet('menu-block-test/hierarchy/parent');
    $this->assertSession()->pageTextContains('parent menu item');
    $this->assertSession()->pageTextContains('child-1 menu item');
    $this->assertSession()->pageTextContains('child-2 menu item');
    $this->assertSession()->pageTextNotContains('child-1-1 menu item');

    $this->drupalGet('admin/structure/block/manage/' . $block_id);
    $this->submitForm([
      'settings[depth]' => 0,
    ], 'Save block');

    // Check if the menu items of level 2 are visible, but not the parent menu
    // item.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('parent menu item');
    $this->assertSession()->pageTextContains('child-1 menu item');
    $this->assertSession()->pageTextContains('child-2 menu item');
    $this->assertSession()->pageTextContains('child-1-1 menu item');
  }

  /**
   * Tests the menu_block expand option.
   */
  public function testMenuBlockExpand() {
    $block_id = 'main';
    $this->drupalGet('admin/structure/block/add/menu_block:main');
    $this->submitForm([
      'id' => $block_id,
      'settings[label]' => 'Main navigation',
      'settings[label_display]' => FALSE,
      'settings[level]' => 1,
      'settings[expand_all_items]' => TRUE,
      'region' => 'primary_menu',
    ], 'Save block');

    // Check if the parent menu item is visible, but the child menu items not.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('parent menu item');
    $this->assertSession()->pageTextContains('child-1 menu item');
    $this->assertSession()->pageTextContains('child-1-1 menu item');
    $this->assertSession()->pageTextContains('child-2 menu item');

    $this->drupalGet('admin/structure/block/manage/' . $block_id);
    $this->submitForm([
      'settings[expand_all_items]' => FALSE,
    ], 'Save block');

    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('parent menu item');
    $this->assertSession()->pageTextNotContains('child-1 menu item');
    $this->assertSession()->pageTextNotContains('child-1-1 menu item');
    $this->assertSession()->pageTextNotContains('child-2 menu item');
  }

  /**
   * Tests the menu_block parent option.
   */
  public function testMenuBlockParent() {
    $block_id = 'main';
    $this->drupalGet('admin/structure/block/add/menu_block:main');
    $this->submitForm([
      'id' => $block_id,
      'settings[label]' => 'Main navigation',
      'settings[label_display]' => FALSE,
      'settings[parent]' => 'main:' . $this->links['parent'],
      'region' => 'primary_menu',
    ], 'Save block');

    $this->drupalGet('<front>');
    $this->assertSession()->pageTextNotContains('parent menu item');
    $this->assertSession()->pageTextContains('child-1 menu item');
    $this->assertSession()->pageTextNotContains('child-1-1 menu item');

    $this->drupalGet('admin/structure/block/manage/' . $block_id);
    $this->submitForm([
      'settings[parent]' => 'main:' . $this->links['child-1'],
    ], 'Save block');

    $this->assertSession()->pageTextNotContains('parent menu item');
    $this->assertSession()->pageTextNotContains('child-1 menu item');
    $this->assertSession()->pageTextContains('child-1-1 menu item');
    $this->assertSession()->pageTextContains('child-1-2 menu item');
  }

  /**
   * Tests the menu_block follow and follow_parent option.
   */
  public function testMenuBlockFollow() {
    $block_id = 'main';
    $this->drupalGet('admin/structure/block/add/menu_block:main');
    $this->submitForm([
      'id' => $block_id,
      'settings[label]' => 'Main navigation',
      'settings[label_display]' => FALSE,
      'settings[follow]' => TRUE,
      'settings[follow_parent]' => 'child',
      'region' => 'primary_menu',
    ], 'Save block');

    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('parent menu item');
    $this->assertSession()->pageTextNotContains('child-1 menu item');
    $this->assertSession()->pageTextNotContains('child-1-1 menu item');

    $this->drupalGet('menu-block-test/hierarchy/parent');
    $this->assertSession()->pageTextNotContains('parent menu item');
    $this->assertSession()->pageTextContains('child-1 menu item');
    $this->assertSession()->pageTextContains('child-2 menu item');

    $this->drupalGet('menu-block-test/hierarchy/parent/child-1');
    $this->assertSession()->pageTextNotContains('parent menu item');
    $this->assertSession()->pageTextNotContains('child-1 menu item');
    $this->assertSession()->pageTextContains('child-1-1 menu item');
    $this->assertSession()->pageTextContains('child-1-2 menu item');
    $this->assertSession()->pageTextNotContains('child-2 menu item');

    $this->drupalGet('admin/structure/block/manage/' . $block_id);
    $this->submitForm([
      'settings[follow_parent]' => 'active',
    ], 'Save block');

    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('parent menu item');
    $this->assertSession()->pageTextNotContains('child-1 menu item');
    $this->assertSession()->pageTextNotContains('child-1-1 menu item');

    $this->drupalGet('menu-block-test/hierarchy/parent');
    $this->assertSession()->pageTextContains('parent menu item');
    $this->assertSession()->pageTextContains('child-1 menu item');
    $this->assertSession()->pageTextNotContains('child-1-1 menu item');
  }

  /**
   * Tests the menu_block suggestion option.
   */
  public function testMenuBlockSuggestion() {
    $block_id = 'main';
    $this->drupalGet('admin/structure/block/add/menu_block:main');
    $this->submitForm([
      'id' => $block_id,
      'settings[label]' => 'Main navigation',
      'settings[label_display]' => FALSE,
      'settings[suggestion]' => 'mainnav',
      'region' => 'primary_menu',
    ], 'Save block');

    /** @var \Drupal\block\BlockInterface $block */
    $block = $this->blockStorage->load($block_id);
    $plugin = $block->getPlugin();

    // Check theme suggestions for block template.
    $variables = [];
    $variables['elements']['#configuration'] = $plugin->getConfiguration();
    $variables['elements']['#plugin_id'] = $plugin->getPluginId();
    $variables['elements']['#id'] = $block->id();
    $variables['elements']['#base_plugin_id'] = $plugin->getBaseId();
    $variables['elements']['#derivative_plugin_id'] = $plugin->getDerivativeId();
    $variables['elements']['content'] = [];
    $suggestions = $this->moduleHandler->invokeAll('theme_suggestions_block', [$variables]);

    $base_theme_hook = 'menu_block';
    $hooks = [
      'theme_suggestions',
      'theme_suggestions_' . $base_theme_hook,
    ];
    $this->moduleHandler->alter($hooks, $suggestions, $variables, $base_theme_hook);

    $this->assertSame($suggestions, [
      'block__menu_block',
      'block__menu_block',
      'block__menu_block__main',
      'block__main',
      'block__menu_block__mainnav',
    ], 'Found expected block suggestions.');

    // Check theme suggestions for menu template.
    $variables = [
      'menu_name' => 'main',
      'menu_block_configuration' => $plugin->getConfiguration(),
    ];
    $suggestions = $this->moduleHandler->invokeAll('theme_suggestions_menu', [$variables]);

    $base_theme_hook = 'menu';
    $hooks = [
      'theme_suggestions',
      'theme_suggestions_' . $base_theme_hook,
    ];
    $this->moduleHandler->alter($hooks, $suggestions, $variables, $base_theme_hook);
    $this->assertSame($suggestions, [
      'menu__main',
      'menu__mainnav',
    ], 'Found expected menu suggestions.');
  }

  /**
   * Test menu block label type options.
   */
  public function testMenuBlockTitleOptions() {
    // Create a block, and edit it repeatedly to test the title display options.
    $block_id = 'main';
    $this->drupalGet('admin/structure/block/add/menu_block:main');
    $this->submitForm([
      'id' => $block_id,
      'settings[label]' => 'Block title',
      'settings[label_display]' => TRUE,
      'settings[label_link]' => FALSE,
      'settings[parent]' => 'main:' . $this->links['child-1'],
      'region' => 'primary_menu',
    ], 'Save block');

    $options = [
      'block label' => [
        'option' => MenuBlock::LABEL_BLOCK,
        'title' => 'Block title',
      ],
      'menu label' => [
        'option' => MenuBlock::LABEL_MENU,
        'title' => 'Main navigation',
      ],
      'fixed menu item' => [
        'option' => MenuBlock::LABEL_FIXED,
        'title' => 'child-1 menu item',
      ],
      'fixed menu item as link' => [
        'option' => MenuBlock::LABEL_FIXED,
        'title' => 'child-1 menu item',
        'label_link' => TRUE,
      ],
      'fixed menu item parent' => [
        'option' => MenuBlock::LABEL_FIXED,
        'title' => 'child-1 menu item',
        'test_link' => 'menu-block-test/hierarchy/parent',
      ],
      'active item' => [
        'option' => MenuBlock::LABEL_ACTIVE_ITEM,
        'title' => 'child-1-1 menu item',
      ],
      'active item as link' => [
        'option' => MenuBlock::LABEL_ACTIVE_ITEM,
        'title' => 'child-1-1 menu item',
        'label_link' => TRUE,
      ],
      'parent item' => [
        'option' => MenuBlock::LABEL_PARENT,
        'title' => 'child-1 menu item',
      ],
      'parent item as link' => [
        'option' => MenuBlock::LABEL_PARENT,
        'title' => 'child-1 menu item',
        'label_link' => TRUE,
      ],
      'parent item top level' => [
        'option' => MenuBlock::LABEL_PARENT,
        'title' => 'parent menu item',
        'test_link' => 'menu-block-test/hierarchy/parent',
      ],
      'parent item 2' => [
        'option' => MenuBlock::LABEL_PARENT,
        'title' => 'parent menu item',
        'test_link' => 'menu-block-test/hierarchy/parent/child-1',
      ],
      'parent item 3' => [
        'option' => MenuBlock::LABEL_PARENT,
        'title' => 'child-1 menu item',
        'test_link' => 'menu-block-test/hierarchy/parent/child-1/child-1-2',
      ],
      'menu root' => [
        'option' => MenuBlock::LABEL_ROOT,
        'title' => 'parent menu item',
      ],
      'menu root as link' => [
        'option' => MenuBlock::LABEL_ROOT,
        'title' => 'parent menu item',
        'label_link' => TRUE,
      ],
      'menu root 2' => [
        'option' => MenuBlock::LABEL_ROOT,
        'title' => 'parent menu item',
        'test_link' => 'menu-block-test/hierarchy/parent/child-1',
      ],
      'menu root 3' => [
        'option' => MenuBlock::LABEL_ROOT,
        'title' => 'parent menu item',
        'test_link' => 'menu-block-test/hierarchy/parent/child-1/child-1-2',
      ],
      'menu root hidden title' => [
        'option' => MenuBlock::LABEL_ROOT,
        'title' => 'parent menu item',
        'label_display' => FALSE,
      ],
    ];

    foreach ($options as $case_id => $option) {
      // The 'label_display' setting should be TRUE if not defined explicitly.
      $label_display = $option['label_display'] ?? TRUE;
      // The 'label_link' setting should default to FALSE.
      $label_link = $option['label_link'] ?? FALSE;
      $this->drupalGet('admin/structure/block/manage/main');
      $this->submitForm([
        'settings[label_type]' => $option['option'],
        'settings[label_display]' => $label_display,
        'settings[label_link]' => $label_link,
      ], 'Save block');
      $test_link = empty($option['test_link']) ? 'menu-block-test/hierarchy/parent/child-1/child-1-1' : $option['test_link'];
      $this->drupalGet($test_link);

      // Find the h2 associated with the main menu block.
      $block_label = $this->assertSession()->elementExists('css', 'h2#block-main-menu');
      // Check that the title is correct.
      $this->assertEquals($option['title'], $block_label->getText(), "Test case '$case_id' should have the right title.");
      // There is no notHasClass(), so we check for the "visually-hidden" class
      // and invert it to determine if the block title is visible or not.
      $visible = !$block_label->hasClass('visually-hidden');
      $this->assertEquals($label_display, $visible, "Test case '$case_id' should have the right visibility.");

      if ($label_link) {
        $this->assertStringContainsString('<a href="', $block_label->getHtml(), "Test case '$case_id' should have a link in the block title.");
      }
      else {
        $this->assertStringNotContainsString('<a href="', $block_label->getHtml(), "Test case '$case_id' should not have a link in the block title.");
      }
    }
  }

}
