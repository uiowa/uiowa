<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

/**
 * Provides functions for processing media in source plugins.
 */
trait ProcessMediaTrait {

  /**
   * Regex to find Drupal 7 JSON for inline embedded files.
   */
  public function entityReplace($match) {
    $fid = $match[1];
    $file_data = $this->fidQuery($fid);
    if ($file_data) {
      $uuid = $this->getMid($file_data['filename'])['uuid'];
      return $this->constructInlineEntity($uuid);
    }
    // Failed to find a file, so let's leave the content unchanged.
    return $match;
  }

  /**
   * Simple query to get info on the Drupal 7 file based on fid.
   */
  public function fidQuery($fid) {
    $query = $this->select('file_managed', 'f')
      ->fields('f', ['filename'])
      ->condition('f.fid', $fid);
    $results = $query->execute();
    return $results->fetchAssoc();
  }

  /**
   * Fetch the media uuid based on the provided filename.
   */
  public function getMid($filename) {
    $connection = \Drupal::database();
    $query = $connection->select('file_managed', 'f');
    $query->join('media__field_media_image', 'fmi', 'f.fid = fmi.field_media_image_target_id');
    $query->join('media', 'm', 'fmi.entity_id = m.mid');
    $result = $query->fields('m', ['uuid'])
      ->condition('f.filename', $filename)
      ->execute();
    return $result->fetchAssoc();
  }

  /**
   * Build the new inline embed entity format for Drupal 8 images.
   */
  public function constructInlineEntity($uuid) {
    $parts = [
      '<drupal-entity',
      'data-embed-button="media_entity_embed"',
      'data-entity-embed-display="view_mode:media.full"',
      'data-entity-embed-display-settings=""',
      'data-entity-type="media"',
      'data-entity-uuid="' . $uuid . '"',
      'data-langcode="en">',
      '</drupal-entity>',
    ];
    return implode(" ", $parts);
  }

  /**
   * Fetch the media id based on the original site's fid.
   */
  protected function getFid($original_fid) {
    $connection = \Drupal::database();
    $query = $connection->select('migrate_map_d7_file', 'mm');
    $query->join('media__field_media_image', 'fmi', 'mm.destid1 = fmi.field_media_image_target_id');
    $result = $query->fields('fmi', ['entity_id'])
      ->condition('mm.sourceid1', $original_fid)
      ->execute();
    $new_fid = $result->fetchField();
    return $new_fid;
  }

}
