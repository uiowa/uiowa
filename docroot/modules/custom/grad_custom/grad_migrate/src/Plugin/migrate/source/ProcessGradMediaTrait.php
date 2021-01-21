<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Provides Grad-specific media processing methods.
 */
trait ProcessGradMediaTrait {

  use ProcessMediaTrait;

  /**
   * Get the source path from the migration config.
   */
  protected function getSourceBasePath() {
    if (isset($this->configuration['constants']) && isset($this->configuration['constants']['SOURCE_BASE_PATH'])) {
      return $this->configuration['constants']['SOURCE_BASE_PATH'];
    }

    return '';
  }

  /**
   * Return the path we'll be writing to.
   */
  protected function getDrupalFileDirectory() {
    return 'public://' . date('Y-m') . '/';
  }

  /**
   * Process an image field.
   */
  protected function processImageField(&$row, $field_name) {
    // Check if an image was attached, and if so, update with new fid.
    $original_fid = $row->getSourceProperty("{$field_name}_fid");

    if (isset($original_fid)) {
      $uri = $this->fidQuery($original_fid)['uri'];
      $filename = str_replace('public://', '', $uri);
      // @todo need to split this up into filepath and filename.
      $filename = explode('/', $filename);
      $filename = end($filename);
      // Get a connection for the destination database.
      $dest_connection = \Drupal::database();
      $dest_query = $dest_connection->select('file_managed', 'f');
      $new_fid = $dest_query->fields('f', ['fid'])
        ->condition('f.filename', $filename)
        ->execute()
        ->fetchField();

      $meta = [
        'alt' => $row->getSourceProperty("{$field_name}_alt"),
        'title' => $row->getSourceProperty("{$field_name}_title"),
      ];

      if (!$new_fid) {
        $new_fid = $this->downloadFile($filename, $this->getSourceBasePath(), $this->getDrupalFileDirectory());
        if ($new_fid) {
          $mid = $this->createMediaEntity($new_fid, $meta, 1);
        }
      }
      else {
        $mid = $this->getMid($filename);
        // And in case we had the file, but not the media entity.
        if (!$mid) {
          $mid = $this->createMediaEntity($new_fid, $meta, 1);
        }
      }
      $row->setSourceProperty("{$field_name}_fid", $mid);
    }
  }

}
