<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

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
      $results = $query->condition("{$first_field}.revision_id", $item['revision_id'])
        ->execute()
        ->fetchAssoc();
      if ($results) {
        $item += $results;
      }
    }
  }

  /**
   * Helper function to retrieve D7 field collection values.
   */
  protected function getFieldCollectionFieldReferences(&$items, string $field) {
    foreach ($items as &$item) {
      $query = $this->select("field_data_field_{$field}", $field)
        ->fields($field, ["field_{$field}_value", "field_{$field}_revision_id"]);
      $results = $query->condition("{$field}.revision_id", $item['revision_id'], '=')
        ->execute()
        ->fetchAssoc();
      if ($results) {
        foreach ($results as $key => $value) {
          $results[str_replace("field_{$field}_", '', $key)] = $value;
          unset($results[$key]);
        }
        $item["field_{$field}"] = $results;
      }
    }
  }

  /**
   * Helper function to create a paragraph.
   *
   * @param $data
   *   The paragraph content and options.
   *
   * @return array
   *   The ID and revision ID of the paragraph.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createParagraph($data) {
    $paragraph = \Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->create($data);

    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId()
    ];
  }
}
