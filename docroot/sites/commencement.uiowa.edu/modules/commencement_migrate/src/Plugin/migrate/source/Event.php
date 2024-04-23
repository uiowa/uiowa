<?php

namespace Drupal\commencement_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "commencement_event",
 *   source_module = "node"
 * )
 */
class Event extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    foreach ([
      'field_event_other_celebrations',
    ] as $source_field) {
      if ($values = $row->getSourceProperty($source_field)) {
        if (!isset($values)) {
          continue;
        }
        $tids = [];
        foreach ($values as $tid_array) {
          $tids[] = $tid_array['tid'];
        }
        // Fetch tag names based on TIDs from our old site.
        $tag_results = $this->select('taxonomy_term_data', 't')
          ->fields('t', ['name'])
          ->condition('t.tid', $tids, 'IN')
          ->execute();
        $new_tids = [];
        foreach ($tag_results as $result) {
          $tag_name = $result['name'];
          $tag = $this->fetchTag($tag_name, $source_field, $row);
          if ($tag !== FALSE) {
            $new_tids[] = $tag;
          }
        }
        $row->setSourceProperty("{$source_field}_processed", $new_tids);
      }
    }

    return TRUE;
  }

  /**
   * Helper function to fetch existing tags.
   */
  private function fetchTag($tag_name, $source_field, $row) {

    $taxonomy_name = NULL;
    if ($source_field === 'field_event_other_celebrations') {
      $taxonomy_name = 'celebrations';
    }

    if ($taxonomy_name !== NULL) {
      // Check if we already have the tag in the destination.
      $result = \Drupal::database()
        ->select('taxonomy_term_field_data', 't')
        ->fields('t', ['tid'])
        ->condition('t.name', $tag_name, '=')
        ->condition('t.vid', $taxonomy_name, '=')
        ->execute()
        ->fetchField();
      if ($result) {
        return $result;
      }
      // If we didn't have the tag already,
      // add a notice to the migration, and return a null.
      $message = 'Taxonomy term failed to migrate. Missing term was: ' . $tag_name;
      $this->migration
        ->getIdMap()
        ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
      return FALSE;
    }
  }

}
