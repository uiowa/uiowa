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
   * Prepare row used for altering source data prior to insertion.
   */
  public function prepareRow(Row $row) {

    // Get mid from fid for profile image.
    $fid = $row->getSourceProperty('field_person_image_fid');
    if ($fid) {
      $mid = $this->profileImage($fid)['entity_id'];
    }
    if ($mid) {
      $row->setSourceProperty('person_mid', $mid);
    }

    // Check summary, and create one if none exists.
    if (!$row->getSourceProperty('field_person_bio_summary')) {
      $content = $row->getSourceProperty('field_person_bio_value');
      $new_summary = $this->extractSummaryFromText($content);
      $row->setSourceProperty('field_person_bio_summary', $new_summary);
    }

    // Call the parent prepareRow.
    return parent::prepareRow($row);
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
