<?php

namespace Drupal\Tests\layout_builder_custom\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\layout_builder_custom\EventSubscriber\ReplicateSubscriber;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests revision cleanup after replication.
 */
#[Group('layout_builder_custom')]
class ReplicateSubscriberTest extends UnitTestCase {

  /**
   * Deletes older revision IDs and preserves the current revision.
   */
  public function testRevisionCleanup(): void {
    $node = $this->createMock(Node::class);
    $node->method('getEntityTypeId')->willReturn('node');
    $node->method('id')->willReturn(7);
    $node->method('getRevisionId')->willReturn(103);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())->method('allRevisions')->willReturnSelf();
    $query->expects($this->once())->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->expects($this->once())->method('condition')->with('nid', 7)->willReturnSelf();
    $query->method('execute')->willReturn([101 => 7, 102 => 7, 103 => 7]);

    $deleted = [];
    $storage = $this->createMock(NodeStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('deleteRevision')->willReturnCallback(function ($vid) use (&$deleted): void {
      $deleted[] = $vid;
    });
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('node')->willReturn($storage);

    $subscriber = new ReplicateSubscriber(
      $manager,
      $this->createMock(UuidInterface::class),
      $this->createMock(CurrentPathStack::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
    );
    $method = new \ReflectionMethod($subscriber, 'removeOldRevisions');
    $method->invoke($subscriber, $node);

    $this->assertSame([101, 102], $deleted);
  }

}
