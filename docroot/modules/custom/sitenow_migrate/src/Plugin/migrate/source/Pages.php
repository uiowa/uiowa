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
    $content = $row->getSourceProperty('body');

    if (isset($content[0])) {
      $content[0]['value'] = preg_replace_callback("|\[\[\{.*?\"fid\":\"(.*?)\".*?\]\]|", [
        $this,
        'entityReplace',
      ], $content[0]['value']);

      $row->setSourceProperty('body', $content);

      // Check summary, and create one if none exists.
      if (isset($content[0]['summary']) && !empty($content[0]['summary'])) {
        $row->setSourceProperty('body_summary', $content[0]['summary']);
      }
      else {
        $new_summary = $this->extractSummaryFromText($content[0]['value']);
        $row->setSourceProperty('body_summary', $new_summary);
      }
    }

    return TRUE;
  }

}
