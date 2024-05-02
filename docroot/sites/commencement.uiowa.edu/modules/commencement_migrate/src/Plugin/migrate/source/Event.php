<?php

namespace Drupal\commencement_migrate\Plugin\migrate\source;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
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
   * A timezone object for use in date handling.
   *
   * @var null|\DateTimeZone
   */
  protected $timezone = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    $this->timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Skip over the rest of the preprocessing, as it's not needed
    // for redirects. Also avoids duplicating the notices.
    // Return TRUE because the row should be created.
    if ($this->migration->id() === 'commencement_event_redirects') {
      return TRUE;
    }

    $source_date = $row->getSourceProperty('field_event_date');
    if (isset($source_date)) {
      $row->setSourceProperty('field_event_date', \DateTime::createFromFormat('Y-m-d H:i:s', $source_date[0]['value'], $this->timezone)->getTimestamp());
    }

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

    // Process the livestream and ceremony information details fields.
    $livestream = '';
    $ceremony_info_target_ids = [];
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

        $this->getFieldCollectionFieldReferences($items, 'snp_section_content_blocks');
        foreach ($items as $item) {
          if (isset($item['field_snp_section_content_blocks'])) {
            $blocks = [$item['field_snp_section_content_blocks']];
            // Get the references for any accordion items.
            $this->getFieldCollectionFieldReferences($blocks, 'snp_accordion_items');
            foreach ($blocks as $block) {
              if (isset($block['field_snp_accordion_items'])) {
                $accordions = [$block['field_snp_accordion_items']];
                // Get the values to populate the details.
                $this->getFieldCollectionFieldValues($accordions, [
                  'snp_accordion_body',
                  'snp_accordion_title',
                ]);

                foreach ($accordions as $accordion) {
                  try {
                    // Create a paragraph and assign its IDs as the value.
                    $ceremony_info_target_ids[] = $this->createParagraph([
                      'type' => 'uiowa_collection_item',
                      'field_collection_headline' => $accordion['field_snp_accordion_title_value'],
                      'field_collection_body' => [
                        'value' => $accordion['field_snp_accordion_body_value'],
                        'format' => 'minimal',
                      ],
                    ]);
                  }
                  catch (InvalidPluginDefinitionException | PluginNotFoundException | EntityStorageException $e) {
                    $this->getLogger('commencement_migrate')->error($this->t('There was an error creating a collection item paragraph, details: @error', [
                      '@error' => $e->getMessage(),
                    ]));
                  }
                }
              }
            }
          }
        }
      }
    }
    $row->setSourceProperty('livestream_processed', $livestream);
    $row->setSourceProperty('ceremony_information_processed', $ceremony_info_target_ids);

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
          'target_id' => $this->processFileField($file_info['fid'], $file_info),
        ];
        unset($file_info['fid']);
      }
    }
    // Set source property value.
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
