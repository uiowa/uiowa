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

    return TRUE;

  }





}
