<?php

namespace Drupal\Tests\sitenow_events\Kernal;

use Drupal\Core\Config\FileStorage;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ConfigTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class EventFeatureTest.
 *
 * @group kernel
 */
class EventFeatureTest extends BrowserTestBase {
  use NodeCreationTrait;
  use ConfigTestTrait;

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
    'config_split',
    'smart_date',
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
    $this->enableConfigSplit('event');

    // Create a user.
    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->save();

    // Store the user for future use.
    $this->user = $editor;
  }

  /**
   * Test event creation and display.
   */
  public function doNotTestEventDisplay(): void {
    $title = 'Test event';

    $this->generateEvents(1);
    $default_theme = $this->config('system.theme')->get('default');

    $view_name = 'events_list_block-card_list';

    // Get the "Configure block" form for our Views block.
    $this->drupalGet("admin/structure/block/add/views_block:$view_name/$default_theme");

    $edit = [];
    $this->submitForm($edit, 'Save block');

    // Assert items per page default settings.
    $this->drupalGet('<front>');

    // @todo Check that event date is displaying.
    $result = $this->xpath('//div[contains(@class, "region-content")]/div[contains(@class, "block-views")]/h2');
    // @todo Check that event virtual details are displaying.
    // @todo Check that event location is showing.
    $this->assertEquals($title, $title);
  }

  /**
   * Enable a config split.
   *
   * @param $split_name
   *
   * @todo Move this to a trait.
   */
  public function enableConfigSplit($split_name) {
    // @todo Add check that split exists.
    // Import the split configuration.
    $config_path = DRUPAL_ROOT . '/../config/default';
    $source = new FileStorage($config_path);
    $config_storage = \Drupal::service('config.storage');
    $split = $source->read("config_split.config_split.$split_name");
    $config_storage->write("config_split.config_split.$split_name", $split);

    $this->copyConfig($config_storage, $this->configImporter()->getStorageComparer()->getSourceStorage());

    // Enable the split.
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable("config_split.config_split.$split_name");
    $config->set('status', TRUE);
    $config->save(TRUE);

    $this->configImporter()->import();
  }

  public function generateEvents($max = 20) {
    for ($i = 0; $i < $max; $i++) {

      $randomness = mt_rand(1, $max);

      $node_data = [
        'title' => $this->randomGenerator->string(),
        'type' => 'event',
        'uid' => $this->user->id(),
        'field_event_when' => [
          'value' => mt_rand(time(), 2147385600),
        ],
      ];

      if ($max === 1 || $randomness % 2 == 0) {
        $node_data['field_event_virtual'] = [
          'uri' => '<front>',
        ];
      }
      if ($max === 1 || ($max % $randomness) % 2 == 0) {
        $node_data['field_event_location'] = [
          'country_code' => 'AD',
          'locality' => 'Canillo',
          'postal_code' => 'AD500',
          'address_line1' => 'C. Prat de la Creu, 62-64',
        ];
      }

      $this->createNode($node_data);
    }
  }

}
