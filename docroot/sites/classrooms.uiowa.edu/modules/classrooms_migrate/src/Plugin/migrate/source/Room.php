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
   * Fields with multiple values that need to be fetched.
   *
   * @var array
   */
  protected $multiValueFields = [
    'field_room_images' => 'field_room_images_fid',
  ];

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

    // Process the furniture details.
    $furniture_items = $row->getSourceProperty('field_room_classroom_furniture');
    $row->setSourceProperty(
      'field_room_classroom_furniture',
      $this->processFieldCollection($furniture_items, 'furniture')
    );

    // Process the tile details.
    $tile_items = $row->getSourceProperty('field_room_tile_details');
    $row->setSourceProperty(
      'field_room_tile_details',
      $this->processFieldCollection($tile_items, 'tile_details')
    );

    // Process the design details.
    $design_items = $row->getSourceProperty('field_room_design_details');
    $row->setSourceProperty(
      'field_room_tile_details',
      $this->processFieldCollection($design_items, 'design')
    );

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

  /**
   * Helper function to snag field collection data and concatenate it.
   *
   * @return array
   *   Array of concatenated field collection details.
   */
  private function processFieldCollection($items, $db_label): array {
    $concat_items = [];
    foreach ($items as $item) {
      $query = $this->select("field_data_field_{$db_label}_type", 't');
      $query->join("field_data_field_{$db_label}_details", 'd', 't.revision_id = d.revision_id');
      $results = $query->condition('t.revision_id', $item['revision_id'], '=')
        ->fields('t', ["field_{$db_label}_type_value"])
        ->fields('d', ["field_{$db_label}_details_value"])
        ->execute()
        ->fetchAll();
      $concat_items[] = implode(' - ', array_values($results[0]));
    }

    return $concat_items;
  }

}
