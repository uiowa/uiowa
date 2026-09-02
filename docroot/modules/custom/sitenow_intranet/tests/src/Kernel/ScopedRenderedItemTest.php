<?php

namespace Drupal\Tests\sitenow_intranet\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\sitenow_intranet\Search\ScopedRenderedItem;

/**
 * Tests that the scoped processor is the one Search API builds.
 *
 * The unit test builds the event by hand, so it proves the subscriber handles
 * a definition correctly but not that Search API still hands it one. Should
 * the event stop firing upstream, the swap would quietly stop happening with
 * every other test still passing. This is the one that would fail.
 *
 * @group sitenow_intranet
 * @coversDefaultClass \Drupal\sitenow_intranet\EventSubscriber\ScopedRenderedItemSubscriber
 */
class ScopedRenderedItemTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'sitenow_intranet',
    'system',
    'user',
  ];

  /**
   * The processor plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $processorManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->processorManager = $this->container
      ->get('plugin.manager.search_api.processor');
  }

  /**
   * Test that the definition points at the scoped subclass.
   */
  public function testDefinitionUsesTheScopedClass() {
    $definition = $this->processorManager->getDefinition('rendered_item');

    $this->assertSame(ScopedRenderedItem::class, $definition['class']);
  }

  /**
   * Test that the manager actually builds the scoped subclass.
   */
  public function testManagerBuildsTheScopedClass() {
    $processor = $this->processorManager
      ->createInstance('rendered_item', ['#index' => NULL]);

    $this->assertInstanceOf(ScopedRenderedItem::class, $processor);
  }

}
