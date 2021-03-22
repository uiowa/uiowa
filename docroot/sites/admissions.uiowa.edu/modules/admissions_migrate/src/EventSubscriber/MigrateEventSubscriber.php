<?php

namespace Drupal\admissions_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Admissions Migrate event subscriber.
 */
class MigrateEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity_type.manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::POST_ROW_SAVE => ['onPostRowSave'],
      MigrateEvents::POST_ROLLBACK => ['onPostRollback'],
    ];
  }

  /**
   * Delete entities not tracked in migrate map after rolling back.
   *
   * @param \Drupal\migrate\Event\MigrateRollbackEvent $event
   *   The post rollback event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onPostRollback(MigrateRollbackEvent $event) {
    $migration = $event->getMigration();

    if ($migration->id() == 'd7_admissions_aos') {
      $entity_types = [
        'paragraph' => [
          'degree',
          'admissions_requirement',
        ],
        'node' => [
          'transfer_tips',
        ],
      ];

      foreach ($entity_types as $type => $bundles) {
        foreach ($bundles as $bundle) {
          $field = ($type == 'taxonomy_term') ? 'vid' : 'type';
          $query = $this->entityTypeManager->getStorage($type)->getQuery();

          $ids = $query
            ->condition($field, $bundle)
            ->execute();

          if ($ids) {
            $controller = $this->entityTypeManager->getStorage($type);
            $entities = $controller->loadMultiple($ids);
            $controller->delete($entities);
          }
        }
      }
    }
  }

  /**
   * Create transfer tips nodes after saving a row so we know the destination.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The migrate post row save event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) {
    $row = $event->getRow();

    if ($tips = $row->getSourceProperty('field_transfer_tips')) {
      $node = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'transfer_tips',
        'field_transfer_tips_aos' => $event->getDestinationIdValues()[0],
        'body' => $row->getSourceProperty('field_transfer_tips'),
        'uid' => 1,
      ]);

      $node->save();
    }
  }

}
