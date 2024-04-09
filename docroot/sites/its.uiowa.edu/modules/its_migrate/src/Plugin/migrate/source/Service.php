<?php

namespace Drupal\its_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "its_service",
 *   source_module = "node"
 * )
 */
class Service extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Make sure our nodes are retrieved in order,
    // and force a highwater mark of our last-most migrated node.
    $query->orderBy('nid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    $fee_info = $row->getSourceProperty('field_ic_fees');
    if (isset($fee_info)) {
      $fee_info[0]['format'] = 'minimal_plus';
      $row->setSourceProperty('field_ic_fees', $fee_info);
    }

    foreach ([
      'field_ic_category',
      'field_audience',
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
          $tag = $this->fetchTag($tag_name, $row);
          if ($tag !== FALSE) {
            $new_tids[] = $tag;
          }
        }
        $row->setSourceProperty("{$source_field}_processed", $new_tids);
      }
    }

    $quick_links = [];

    $ic_obtain = $row->getSourceProperty('field_ic_obtain');
    if(count($ic_obtain) > 0) {
      $quick_links = array_merge($quick_links, $ic_obtain);
    }

    $ic_commonactions = $row->getSourceProperty('field_ic_commonactions');
    if(count($ic_commonactions) > 0) {
      $quick_links = array_merge($quick_links, $ic_commonactions);
    }

    $row->setSourceProperty('quick_links', $quick_links);

    $ic_aliases = $row->getSourceProperty('field_ic_aliases');
    if (count($ic_aliases) > 0) {
      $row->setSourceProperty('aliases', $this->prepareAliases($ic_aliases));
    }

    return TRUE;
  }

  /**
   * Helper function to fetch existing tags.
   */
  public function postImport(MigrateImportEvent $event) {
    parent::postImport($event);

    // If nothing to report, then we're done.
    if (empty($this->reporter)) {
      return;
    }

    // Grab our migration map.
    $db = \Drupal::database();
    if (!$db->schema()->tableExists('migrate_map_' . $this->migration->id())) {
      return;
    }
    $mapper = $db->select('migrate_map_' . $this->migration->id(), 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed();

    // Update a reporter for new node ids based on old entity ids.
    $reporter = [];
    foreach ($this->reporter as $sid => $did) {
      if ($sid === '7831' || $did === '7831') {
        $foo = 'UH SIR????????';
      }
      $reporter[$mapper[$sid]] = $did;
    }

    // Empty it out so it doesn't keep repeating if the postImport
    // runs multiple times, as it sometimes does.
    $this->reporter = [];

    // Spit out a report in the logs/cli.
    foreach ($reporter as $sid => $did) {
      $this->logger->notice('Node: @nid, Image: @filename', [
        '@nid' => $sid,
        '@filename' => $did,
      ]);
    }
  }

  /**
   * Helper function to Prepare service aliases.
   */
  private function prepareAliases($aliases) {
    $aliases_string = '';
    foreach ($aliases as $index => $alias) {
      if ($index !== 0) {
        $aliases_string .= ', ';
      }

      $aliases_string .= $alias["value"];
    }

    return [
      'value' => $aliases_string,
      'format' => 'plain_text'
    ];
  }


  /**
   * Helper function to fetch existing tags.
   */
  private function fetchTag($tag_name, $row) {
    // Check if we already have the tag in the destination.
    $result = \Drupal::database()
      ->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid'])
      // @todo Add another conditional to match the proper vocabulary,
      //   in case there are duplicate terms across different vocabs.
      ->condition('t.name', $tag_name, '=')
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
