<?php

namespace Drupal\classrooms_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

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
  public function query() {
    $query = parent::query();
    // Limit the migration to only those rooms
    // which are published on the source.
    $query->condition('n.status', '1', '=');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Grab the taxonomy term id from the source
    // for the building, process to get the building
    // abbreviation, and lowercase to match the formatting
    // of the destination site.
    $building_tid = $row->getSourceProperty('field_room_building');
    $building_id = strtolower($this->processTag($building_tid));

    // Check if we have the building id in place.
    $building = \Drupal::entityTypeManager()
      ->getStorage('building')
      ->load($building_id);
    if ($building === NULL) {
      $this->logger->notice($this->t('From original building @building @room, @building not present at destination.', [
        '@building' => $building_id,
        '@room' => $row->getSourceProperty('field_room_number')[0]['value'],
      ]));
      return FALSE;
    }

    $row->setSourceProperty('field_room_building', $building_id);
    return TRUE;

  }

  /**
   * Helper function to snag the building abbreviation.
   */
  private function processTag($building_tid) {
    return $this->select('taxonomy_term_data', 't')
      ->fields('t', ['name'])
      ->condition('t.vid', '2', '=')
      // There should be only one building attached,
      // so grab the first entry of the building_tid array.
      ->condition('t.tid', $building_tid[0], '=')
      ->execute()
      ->fetchField();
  }

}
