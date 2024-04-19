<?php

namespace Drupal\commencement_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
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
  use LinkReplaceTrait;

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

    return TRUE;
  }

  /**
   * Helper function to Prepare event aliases.
   */
  private function prepareAliases($aliases) {
    $aliases_string = implode(', ', array_column($aliases, 'value'));
    return ['value' => $aliases_string];
  }

  /**
   * Helper function to fetch existing tags.
   */
  private function fetchTag($tag_name, $source_field, $row) {
  }

}
