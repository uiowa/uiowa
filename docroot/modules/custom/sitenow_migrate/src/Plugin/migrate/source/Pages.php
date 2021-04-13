<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "pages",
 *  source_module = "node"
 * )
 */
class Pages extends BaseNodeSource {

  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Search for D7 inline embeds and replace with D8 inline entities.
    $body = $row->getSourceProperty('body');

    if (isset($body[0])) {
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);
      $row->setSourceProperty('body', $body);

      // Check summary, and create one if none exists.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    return TRUE;
  }

}
