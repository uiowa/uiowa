<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileSystemInterface;
use Drupal\migrate\MigrateException;
use Drupal\sitenow_migrate\Plugin\migrate\CreateMediaTrait;

/**
 * Provides functions for processing field collections in source plugins.
 */
trait ProcessFieldCollectionTrait {
  /**
   * Helper function to retrieve D7 field collection values.
   */
  protected function getFieldCollectionFieldValues(&$items, $fields) {
    $first_field = array_shift($fields);
    foreach ($items as &$item) {
      $query = $this->select("field_data_field_{$first_field}", $first_field)
        ->fields($first_field, ["field_{$first_field}_value"]);
      foreach ($fields as $additional_field) {
        $query->join("field_data_field_{$additional_field}", $additional_field, "{$first_field}.revision_id = {$additional_field}.revision_id");
        $query->fields($additional_field, ["field_{$additional_field}_value"]);
      }
      $results = $query->condition("{$first_field}.revision_id", $item['revision_id'], '=')
        ->execute()
        ->fetchAssoc();
      if ($results) {
        $item += $results;
      }
    }
  }
}
