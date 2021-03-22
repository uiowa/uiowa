<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "articles",
 *  source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {

  use ProcessMediaTrait;

  /**
   * Prepare row used for altering source data prior to its insertion.
   */
  public function prepareRow(Row $row) {

    // Check if an image was attached, and if so, update with new fid.
    $original_fid = $row->getSourceProperty('field_image_fid');
    if (isset($original_fid)) {
      $row->setSourceProperty('field_image_fid', $this->getFid($original_fid));
    }

    // Search for D7 inline embeds and replace with D8 inline entities.
    $content = $row->getSourceProperty('field_article_body_value');
    $content = preg_replace_callback("|\[\[\{.*?\"fid\":\"(.*?)\".*?\]\]|", [
      $this,
      'entityReplace',
    ], $content);
    $row->setSourceProperty('field_article_body_value', $content);

    // Check summary, and create one if none exists.
    if (!$row->getSourceProperty('field_article_body_summary')) {
      $new_summary = $this->extractSummaryFromText($content);
      $row->setSourceProperty('field_article_body_summary', $new_summary);
    }
    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
