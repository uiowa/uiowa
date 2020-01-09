<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class PostMigrationSubscriber.
 *
 * @package Drupal\sitenow_migrate\EventSubscriber
 */
class PostMigrationSubscriber implements EventSubscriberInterface {

  /**
   * Get subscribed events.
   * 
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    return $events;
  }


  /**
   * Calls for additional processing after each migration has completed.
   *
   * {@inheritdoc}
   */
  public function onMigratePostImport(MigrageImportEvent $event) {
    $migration = $event->getMigration();
    switch ($migration->id()) {

      case 'd7_page':

      case 'd7_file':
      case 'd7_article':
      case 'd7_person':
    }
  }

  public function updateInternalLinks($link_aliases) {
    $connection = \Drupal::database();
    $result = $connection->select('migrate_map_d7_page', 'mm')
    ->fields('mm', ['sourceid1', 'destid1'])
    ->execute();
    
    foreach ($link_aliases as $original_link => $new_link) {
      if (is_numeric($original_link)) {
        // Need mapping of old nid to new nid.
        
      } else {
        // Need to check old alias, and if it matches new alias pattern.
      }
    }
  }

  private function checkForPossibleLinkBreaks() {
    $connection = \Drupal::database();
    $query = $connection->select('node__field_page_content_block', 'n');
    $query->join('paragraph__field_section_content_block', 's', 's.entity_id = n.field_page_content_block_target_id');
    $query->join('paragraph__field_text_body', 'p', 'p.entity_id = s.field_section_content_block_target_id');
    $query->fields('n', ['entity_id'])
      ->condition($query->orConditionGroup()
        ->condition('p.field_text_body_value', "%href%BASE_URL%", 'LIKE')
        ->condition('p.field_text_body_value', "%href%node/%", 'LIKE')
      );
    $result = $query->execute();


    return $result->fetchAssoc();
  }
}