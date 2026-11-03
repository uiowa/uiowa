<?php

namespace Drupal\Tests\sitenow_p2lb\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorageInterface;
use Drupal\sitenow_p2lb\P2LbDeleteRevisions;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests P2LB revision cleanup.
 */
#[Group('sitenow_p2lb')]
class P2LbDeleteRevisionsTest extends UnitTestCase {

  /**
   * Deletes eligible revision IDs while preserving later revisions.
   */
  public function testDeletesEligibleRevisionIds(): void {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn('102');

    $node = $this->createMock(Node::class);
    $node->method('id')->willReturn(7);
    $node->method('get')
      ->with('field_v3_conversion_revision_id')
      ->willReturn($field);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())->method('allRevisions')->willReturnSelf();
    $query->expects($this->once())->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->expects($this->once())->method('condition')->with('nid', 7)->willReturnSelf();
    $query->method('execute')->willReturn([101 => 7, 102 => 7, 103 => 7]);

    $deleted = [];
    $storage = $this->createMock(NodeStorageInterface::class);
    $storage->expects($this->once())->method('load')->with(7)->willReturn($node);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('deleteRevision')->willReturnCallback(function ($vid) use (&$deleted): void {
      $deleted[] = $vid;
    });

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('node')->willReturn($storage);
    $repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $repository->method('getEntityTypeFromClass')->with(Node::class)->willReturn('node');

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('sitenow_p2lb')->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);
    $container->set('entity_type.repository', $repository);
    $container->set('logger.factory', $logger_factory);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $context = [];
    P2LbDeleteRevisions::deleteRevisions(1, [7], $context);

    $this->assertSame([101, 102], $deleted);
  }

}
