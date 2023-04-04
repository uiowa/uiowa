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

}
