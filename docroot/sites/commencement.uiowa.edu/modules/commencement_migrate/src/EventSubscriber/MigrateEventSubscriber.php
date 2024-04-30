<?php

namespace Drupal\commencement_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * UIPress Migrate event subscriber.
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

    if ($migration->id() === 'uipress_books') {
      $entity_types = [
        'paragraph' => [
          'uiowa_collection_item',
        ],
      ];

      foreach ($entity_types as $type => $bundles) {
        foreach ($bundles as $bundle) {
          $field = ($type === 'taxonomy_term') ? 'vid' : 'type';
          $query = $this->entityTypeManager->getStorage($type)->getQuery();

          $ids = $query
            ->condition($field, $bundle)
            ->accessCheck()
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

}
