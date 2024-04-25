<?php

namespace Drupal\commencement_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessFieldCollectionTrait;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "commencement_event",
 *   source_module = "node"
 * )
 */
class Event extends BaseNodeSource {
  use ProcessMediaTrait;
  use ProcessFieldCollectionTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Mapping taxonomy term to taxonomy term reference field.
    foreach ([
      'field_event_other_celebrations',
      'field_session',
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

    // Mapping taxonomy term to node entity reference field.
    foreach ([
      'field_event_location_ref',
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
        $new_node = [];
        foreach ($tag_results as $result) {
          $tag_name = $result['name'];
          $tag = $this->fetchNode($tag_name, $source_field, $row);
          if ($tag !== FALSE) {
            $new_node[] = $tag;
          }
        }
        $row->setSourceProperty("{$source_field}_processed", $new_node);
      }
    }

    // Process the livestream field.
    $livestream = '';
    if ($items = $row->getSourceProperty('field_snp_sections')) {
      if (!empty($items)) {
        $this->getFieldCollectionFieldValues($items, ['snp_section_body']);
        foreach ($items as $item) {
          if (isset($item['field_snp_section_body_value'])) {
            // Replace inline media embeds.
            $livestream = $this->replaceInlineFiles($item['field_snp_section_body_value']);
            if ($livestream != $item['field_snp_section_body_value']) {
              break;
            }
            $livestream = '';
          }
        }
      }
    }
    $row->setSourceProperty('livestream_processed', $livestream);

    // Process order of events field.
    $order_of_event_processed = NULL;
    $session = $row->getSourceProperty('field_session');
    $college = $row->getSourceProperty('field_event_department');

    if ($session && $college) {
      // Query D7 file table.
      $query = $this->select('file_managed', 'f');
      $query->join('field_data_field_document_order_of_events', 'o',
        'o.entity_id = f.fid');
      $query->join('field_data_field_document_session', 's',
        's.entity_id = f.fid');
      $query->join('field_data_field_document_college', 'c',
        'c.entity_id = f.fid');

      // Get file information.
      $file_info = $query
        ->fields('f')
        ->condition('o.field_document_order_of_events_value', TRUE)
        ->condition('s.field_document_session_tid', $session[0]['tid'])
        ->condition('c.field_document_college_tid', $college[0]['tid'])
        ->execute()
        ->fetchAssoc();

      if ($file_info) {
        // Create local copy.
        $order_of_event_processed = [
          'target_id' => $this->processFileField($file_info['fid'], $file_info)
        ];
        unset($file_info['fid']);
      }
    }
    // Set source property value
    $row->setSourceProperty('order_of_event_processed', $order_of_event_processed);

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

    if ($source_field === 'field_session') {
      $taxonomy_name = 'session';
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

  /**
   * Helper function to fetch existing nodes.
   */
  private function fetchNode($tag_name, $source_field, $row) {

    $node_name = NULL;
    if ($source_field === 'field_event_location_ref') {
      $node_name = 'venue';
    }

    if ($node_name !== NULL) {
      // Check if we already have the tag in the destination.
      $result = \Drupal::database()
        ->select('node_field_data', 'n')
        ->fields('n', ['nid'])
        ->condition('n.title', $tag_name, '=')
        ->condition('n.type', $node_name, '=')
        ->execute()
        ->fetchField();
      if ($result) {
        return $result;
      }
      // If we didn't have the node already,
      // add a notice to the migration, and return a null.
      $message = 'Taxonomy term failed to migrate. Missing node was: ' . $tag_name;
      $this->migration
        ->getIdMap()
        ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
      return FALSE;
    }
  }

}
