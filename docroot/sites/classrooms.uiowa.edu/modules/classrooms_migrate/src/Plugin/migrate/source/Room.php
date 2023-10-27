<?php

namespace Drupal\classrooms_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "classrooms_room",
 *   source_module = "node"
 * )
 */
class Room extends BaseNodeSource {
  use ProcessMediaTrait;

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
      $this->processFieldCollection(
        $furniture_items,
        'furniture',
        [
          'type',
          'details',
        ]
      )
    );

    // Process the tile details.
    $tile_items = $row->getSourceProperty('field_room_tile_details');
    $row->setSourceProperty(
      'field_room_tile_details',
      $this->processFieldCollection(
        $tile_items,
        'tile_details',
        [
          'name',
          'details',
        ]
      )
    );

    // Process the design details.
    $design_items = $row->getSourceProperty('field_room_design_details');
    $row->setSourceProperty(
      'field_room_design_details',
      $this->processFieldCollection(
        $design_items,
        'design_details',
        [
          'name',
          'detail',
        ]
      )
    );

    // Process the gallery.
    $gallery_images = $row->getSourceProperty('field_room_images');
    if (!empty($gallery_images)) {
      $new_images = [];
      foreach ($gallery_images as $gallery_image) {
        $new_images[] = $this->processImageField(
          $gallery_image['fid'],
          $gallery_image['alt'],
          $gallery_image['title'],
        );
      }
      $row->setSourceProperty('featured_image', $new_images[0]);
      $row->setSourceProperty('field_room_images', $new_images);
    }

    // Process the file id part of the seating chart field.
    $seating_chart = $row->getSourceProperty('field_room_seating_chart');
    if (!empty($seating_chart)) {
      $seating_chart[0]['target_id'] = $this->processFileField($seating_chart[0]['fid']);
      unset($seating_chart[0]['fid']);
      $row->setSourceProperty('field_room_seating_chart', $seating_chart);
    }

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
  private function processFieldCollection($items, $db_label, $collection_fields): array {
    $concat_items = [];
    $first_field = array_shift($collection_fields);
    foreach ($items as $item) {
      $query = $this->select("field_data_field_{$db_label}_{$first_field}", $first_field)
        ->fields($first_field, ["field_{$db_label}_{$first_field}_value"]);
      foreach ($collection_fields as $additional_field) {
        $query->join("field_data_field_{$db_label}_{$additional_field}", $additional_field, "{$first_field}.revision_id = {$additional_field}.revision_id");
        $query->fields($additional_field, ["field_{$db_label}_{$additional_field}_value"]);
      }
      $results = $query->condition("{$first_field}.revision_id", $item['revision_id'], '=')
        ->execute()
        ->fetchAll();
      $concat_items[] = implode(' - ', array_values($results[0]));
    }

    return $concat_items;
  }

}
