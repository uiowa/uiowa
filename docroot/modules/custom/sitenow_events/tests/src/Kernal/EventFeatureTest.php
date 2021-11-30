<?php

namespace Drupal\Tests\sitenow_events\Kernal;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class EventFeatureTest.
 *
 * @group kernel
 */
class EventFeatureTest extends EntityKernelTestBase {
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
    'config',
    'config_split',
  ];

  /**
   * A user.
   *
   * @var null
   */
  protected $user = NULL;

  /**
   * Setup tasks.
   */
  public function setUp(): void {
    parent::setUp();
    $this->setInstallProfile('sitenow');
    $this->installConfig(['filter']);
    $this->installSchema('node', ['node_access']);
    // Import the 'event' split configuration.
    $config_path = DRUPAL_ROOT . '/../config/default';
    $source = new FileStorage($config_path);
    $config_storage = \Drupal::service('config.storage');
    $config_storage->write('config_split.config_split.event', $source->read('config_split.config_split.event'));

    // Enable the 'event' split.
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('config_split.config_split.event');
    $config->set('status', TRUE);
    $config->save(TRUE);

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
  public function testEventDisplay(): void {
    $title = 'Test event';

    $node = $this->createNode([
      'title' => $title,
      'type' => 'event',
      'uid' => $this->user->id(),
    ]);

    // @todo Set when, virtual, location fields.
    // @todo Place event view block.
    // @todo Check that event date is displaying.
    // @todo Check that event virtual details are displaying.
    // @todo Check that event location is showing.
    $this->assertEquals($title, $node->getTitle());
  }

}
