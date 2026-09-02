<?php

namespace Drupal\Tests\sitenow_intranet\Unit;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Tests\UnitTestCase;
use Drupal\media\Plugin\Filter\MediaEmbed;
use Drupal\search_api\Event\GatheringPluginInfoEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Plugin\search_api\processor\RenderedItem;
use Drupal\sitenow_intranet\EventSubscriber\ScopedRenderedItemSubscriber;
use Drupal\sitenow_intranet\Render\RecursionGuard;
use Drupal\sitenow_intranet\Search\ScopedRenderedItem;

/**
 * Render recursion guard test.
 *
 * @group sitenow_intranet
 *
 * @coversDefaultClass \Drupal\sitenow_intranet\Render\RecursionGuard
 */
class RecursionGuardTest extends UnitTestCase {

  /**
   * The guards, keyed by the class holding them.
   *
   * @var string[]
   */
  protected const GUARDS = [
    EntityReferenceEntityFormatter::class => 'recursiveRenderDepth',
    MediaEmbed::class => 'recursiveRenderDepth',
  ];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->setGuards([]);

    parent::tearDown();
  }

  /**
   * Test that both counters are emptied.
   */
  public function testResetEmptiesEveryGuard() {
    $this->setGuards(['nodepersonfield_image216media1041' => 21]);

    RecursionGuard::reset();

    foreach (array_keys(static::GUARDS) as $class) {
      $this->assertSame([], $this->getGuard($class), "$class was not reset.");
    }
  }

  /**
   * Test that resetting an already empty counter is harmless.
   */
  public function testResetIsIdempotent() {
    RecursionGuard::reset();
    RecursionGuard::reset();

    foreach (array_keys(static::GUARDS) as $class) {
      $this->assertSame([], $this->getGuard($class), "$class was not reset.");
    }
  }

  /**
   * Test that nothing but a reset brings a count back down.
   *
   * Guards the assumption behind the whole approach: the counters only ever
   * climb, so scoping them means clearing them.
   */
  public function testCountsAreNotSelfClearing() {
    $depth = EntityReferenceEntityFormatter::RECURSIVE_RENDER_LIMIT - 1;
    $this->setGuards(['nodepersonfield_image216media1041' => $depth]);

    foreach (array_keys(static::GUARDS) as $class) {
      $this->assertSame(
        ['nodepersonfield_image216media1041' => $depth],
        $this->getGuard($class),
        "$class did not hold its count."
      );
    }
  }

  /**
   * Test that the reset is scoped to one item, not one batch.
   */
  public function testResetIsScopedToOneItem() {
    $method = new \ReflectionMethod(ScopedRenderedItem::class, 'addFieldValues');

    $this->assertSame(
      ScopedRenderedItem::class,
      $method->getDeclaringClass()->getName(),
      'The per-item render is no longer overridden.'
    );
    $this->assertTrue(
      is_subclass_of(ScopedRenderedItem::class, RenderedItem::class),
      'The processor no longer extends the one it replaces.'
    );
  }

  /**
   * Test that the subclass is swapped in for the stock processor.
   */
  public function testSubscriberSwapsTheProcessorClass() {
    $definitions = [
      'rendered_item' => ['class' => RenderedItem::class],
      'ignorecase' => ['class' => 'Drupal\search_api\Plugin\search_api\processor\Ignorecase'],
    ];

    (new ScopedRenderedItemSubscriber())
      ->swapRenderedItem(new GatheringPluginInfoEvent($definitions));

    $this->assertSame(ScopedRenderedItem::class, $definitions['rendered_item']['class']);
    $this->assertSame(
      'Drupal\search_api\Plugin\search_api\processor\Ignorecase',
      $definitions['ignorecase']['class'],
      'An unrelated processor was altered.'
    );
  }

  /**
   * Test that the swap happens while processors are being gathered.
   */
  public function testSubscribesToGatheringProcessors() {
    $this->assertSame(
      [SearchApiEvents::GATHERING_PROCESSORS => 'swapRenderedItem'],
      ScopedRenderedItemSubscriber::getSubscribedEvents()
    );
  }

  /**
   * Sets every guard to the given value.
   *
   * @param array $value
   *   The counter to set.
   */
  protected function setGuards(array $value): void {
    foreach (static::GUARDS as $class => $name) {
      (new \ReflectionProperty($class, $name))->setValue(NULL, $value);
    }
  }

  /**
   * Reads one guard.
   *
   * @param string $class
   *   The class holding the guard.
   *
   * @return array
   *   The counter.
   */
  protected function getGuard(string $class): array {
    return (new \ReflectionProperty($class, static::GUARDS[$class]))->getValue();
  }

}
