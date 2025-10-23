<?php

namespace Drupal\inrc_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "inrc_nonprofit",
 *   source_module = "node"
 * )
 */
class Nonprofit extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['alias'] = $this->t('The URL alias for this node.');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Skip this node if it comes after our last migrated.
    if ($row->getSourceProperty('nid') < $this->getLastMigrated()) {
      return FALSE;
    }
    parent::prepareRow($row);

    // Skip over the rest of the preprocessing, as it's not needed
    // for redirects. Also avoids duplicating the notices.
    // Return TRUE as long as we have an alias so that
    // the row will be created.
    if ($this->migration->id() === 'inrc_nonprofit_redirects') {
      if ($row->getSourceProperty('alias')) {
        return TRUE;
      }
      return FALSE;
    }

    // Convert the UNIX timestamps into date strings.
    foreach ([
      'field_np_board_res_date',
      'field_np_last_training_date',
    ] as $field) {
      if ($timestamp = $row->getSourceProperty($field)) {
        $row->setSourceProperty($field, date('Y-m-d', $timestamp[0]['value']));
      }
    }

    // Field expiration date is in a YYYY-mm-dd 00:00:00 format,
    // but we don't need the hour:minute:second granularity.
    if ($timestamp = $row->getSourceProperty('field_expiration_date')) {
      $timestamp = substr($timestamp[0]['value'], 0, 10);
      $row->setSourceProperty('field_expiration_date', $timestamp);
    }

    return TRUE;
  }

}
