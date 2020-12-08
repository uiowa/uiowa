<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for pre-rollback migrate event.
 *
 * @package Drupal\sitenow_migrate\EventSubscriber
 */
class MigratePreRollbackEvent implements EventSubscriberInterface {

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The media entity type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The media entity bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * PostRowSaveEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get subscribed events.
   *
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PRE_ROLLBACK][] = ['onMigratePreRollback'];
    return $events;
  }

  /**
   * Calls for removal of media entities associated with files rolling back.
   *
   * {@inheritdoc}
   */
  public function onMigratePreRollback($event) {
    $migration_id = $event->getMigration()->id();
    switch ($migration_id) {

      // Calls for creating a media entity for imported files.
      case 'd7_file':
      case 'd7_grad_file':
        $migrate_map = 'migrate_map_' . $migration_id;
        $this->removeMediaEntities($migrate_map);
        break;
    }
  }

  /**
   * Remove associated media entities prior to file removal.
   */
  public function removeMediaEntities($migrate_map) {
    // Get our destination file ids.
    $connection = Database::getConnection();
    $query = $connection->select($migrate_map, 'mm')
      ->fields('mm', ['destid1']);
    $fids = $query->execute()->fetchCol();

    // Grab our image media entities that reference files to be removed.
    $query1 = $connection->select('media__field_media_image', 'm_image')
      ->fields('m_image', ['entity_id'])
      ->condition('m_image.field_media_image_target_id', $fids, 'in');
    // Grab our file media entities that reference files to be removed.
    $query2 = $connection->select('media__field_media_file', 'm_file')
      ->fields('m_file', ['entity_id'])
      ->condition('m_file.field_media_file_target_id', $fids, 'in');
    $results = $query1->execute()->fetchCol();
    $results = array_merge($results, $query2->execute()->fetchCol());

    $entityManager = $this->entityTypeManager->getStorage('media');
    $mediaEntities = $entityManager->loadMultiple($results);
    $entityManager->delete($mediaEntities);
  }

}
