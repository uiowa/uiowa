<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "people",
 *  source_module = "node"
 * )
 */
class People extends BaseNodeSource {

  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Get mid from fid for profile image.
    $image = $row->getSourceProperty('field_person_image');

    if (!empty($image)) {
      $mid = $this->profileImage($image[0]['fid'])['entity_id'];

      if ($mid) {
        $row->setSourceProperty('person_mid', $mid);
      }
    }

    // Check summary, and create one if none exists.
    $bio = $row->getSourceProperty('field_person_bio');

    if (!empty($bio)) {
      $bio[0]['value'] = $this->replaceInlineFiles($bio[0]['value']);
      $row->setSourceProperty('field_person_bio', $bio);
      $row->setSourceProperty('field_person_bio_summary', $this->getSummaryFromTextField($bio));
    }

    return TRUE;
  }

  /**
   * Fetch the media uuid based on the provided filename.
   */
  public function profileImage($fid) {
    $file_data = $this->fidQuery($fid);
    $filename = $file_data['filename'];
    $connection = \Drupal::database();
    $query = $connection->select('file_managed', 'f');
    $query->join('media__field_media_image', 'fmi', 'f.fid = fmi.field_media_image_target_id');
    $result = $query->fields('fmi', ['entity_id'])
      ->condition('f.filename', $filename)
      ->execute();

    return $result->fetchAssoc();
  }

}
