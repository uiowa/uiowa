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

    return TRUE;
  }

}
