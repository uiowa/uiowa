<?php

namespace Drupal\grad_migrate\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for pre-rollback migrate event.
 *
 * @package Drupal\grad_migrate\EventSubscriber
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

      case 'd7_grad_article':
        $this->removeArticleMedia();
        break;
    }
  }

  /**
   * Removes media entities that were created as part of the article migration.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeArticleMedia() {
    $connection = Database::getConnection();
    $query = $connection->select('migrate_map_d7_grad_article', 'mm');
    $query->join('entity_usage', 'usage', 'mm.destid1 = usage.source_id');
    $query = $query->fields('usage', ['target_id']);
    // @todo check that the media is ONLY used in migrated articles.
    $mids = $query->distinct()
      ->execute()
      ->fetchCol();

    // Delete the media entities.
    // This should include removal of the file usages,
    // which will mark them for deletion on next cleanup.
    $entityManager = $this->entityTypeManager->getStorage('media');
    $mediaEntities = $entityManager->loadMultiple($mids);
    $entityManager->delete($mediaEntities);
  }

}
