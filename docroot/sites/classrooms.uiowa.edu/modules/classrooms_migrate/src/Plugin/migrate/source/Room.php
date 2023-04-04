<?php

namespace Drupal\classrooms_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "classrooms_room",
 *   source_module = "node"
 * )
 */
class Room extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Check if we have the building id in place.
    $building_id = $row->getSourceProperty('field_room_building');
    // Existing site used all caps; new site uses lower.
    $building_id = strtolower($building_id);
    $building = \Drupal::entityTypeManager()
      ->getStorage('building')
      ->load($building_id);
    if ($building === null) {
      $this->logger->notice($this->t('From original building @building @room, @building not present at destination.', [
        '@building' => $building_id,
        '@room' => $row->getSourceProperty('field_room_number'),
      ]));
    }
    $row->setSourceProperty('field_room_building', $building_id);

    return TRUE;
  }

  /**
   * Helper function to check for existing tags and create if they don't exist.
   */
  private function processTag($building_name) {
    // Check if we already have the tag in the destination.
    $building = \Drupal::entityTypeManager()
      ->getStorage('building')
      ->load(strtolower($building_name));
    $result = \Drupal::database()
      ->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid'])
      ->condition('t.vid', 'buildings', '=')
      ->condition('t.name', $tag_name, '=')
      ->execute()
      ->fetchField();
    if ($result) {
      return $result;
    }
    // If we didn't have the tag already,
    // then create a new tag and return its id.
    $term = Term::create([
      'name' => $tag_name,
      'vid' => 'tags',
    ]);
    if ($term->save()) {
      return $term->id();
    }

    // If we didn't save for some reason, add a notice
    // to the migration, and return a null.
    $message = 'Taxonomy term failed to migrate. Missing term was: ' . $tag_name;
    $this->migration
      ->getIdMap()
      ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
    return FALSE;
  }

}
