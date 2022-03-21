<?php

namespace Drupal\Tests\sitenow_events\Kernel;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * People block kernel test.
 *
 * @group kernel
 */
class PeopleBlockViewTest extends BrowserTestBase {
  use NodeCreationTrait;

  /**
   * Additional modules to enable.
   *
   * {@inheritdoc}
   *
   * @var string[]
   */
  public static $modules = [
    'node',
    'address',
    'config',
  ];

  /**
   * A user.
   *
   * @var null
   */
  protected $user = NULL;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'sitenow';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Setup tasks.
   */
  public function setUp(): void {
    parent::setUp();

    // Create a user.
    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->save();

    // Store the user for future use.
    $this->user = $editor;
    $this->drupalLogin($this->user);
  }

  /**
   * Test event creation and display.
   */
  public function testBlockDisplay(): void {
    $title = 'Test event';

    $this->generateEvents(1);
    $default_theme = $this->config('system.theme')->get('default');

    $view_name = 'people_list_block-list_card';

    // Get the "Configure block" form for our Views block.
    $this->drupalGet("admin/structure/block/add/views_block:$view_name/$default_theme");

    $edit = [];
    $this->submitForm($edit, 'Save block');

    // Assert items per page default settings.
    $this->drupalGet('<front>');

    // @todo Check that event date is displaying.
    $this->xpath('//div[contains(@class, "region-content")]/div[contains(@class, "block-views")]/h2');
    // @todo Check that event virtual details are displaying.
    // @todo Check that event location is showing.
    $this->assertEquals($title, $title);
  }

  /**
   * Generate content items.
   *
   * @param int $total
   *   The number of items to create.
   */
  public function generateEvents($total = 20) {
    for ($i = 0; $i < $total; $i++) {

      $node_data = [
        'title' => $this->randomGenerator->string(),
        'type' => 'person',
        'uid' => $this->user->id(),
        'field_person_first_name' => [
          'value' => $this->randomGenerator->name(),
        ],
        'field_person_last_name' => [
          'value' => $this->randomGenerator->name(),
        ],
        'field_person_position' => [
          'value' => $this->randomGenerator->name(),
        ],
      ];

      $this->createNode($node_data);
    }
  }

}
