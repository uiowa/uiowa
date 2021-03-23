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
    parent::prepareRow($row);

    // Check if an image was attached, and if so, update with new fid.
    $image = $row->getSourceProperty('field_image');

    if (!empty($image)) {
      $row->setSourceProperty('field_image_fid', $this->getFid($image[0]['fid']));
    }

    // Search for D7 inline embeds and replace with D8 inline entities.
    $content = $row->getSourceProperty('field_article_body');

    if (!empty($content)) {
      $content[0]['value'] = preg_replace_callback("|\[\[\{.*?\"fid\":\"(.*?)\".*?\]\]|", [
        $this,
        'entityReplace',
      ], $content[0]['value']);

      $row->setSourceProperty('field_article_body', $content);

      // Check summary, and create one if none exists.
      if (empty($content[0]['summary'])) {
        $new_summary = $this->extractSummaryFromText($content[0]['value']);
        $row->setSourceProperty('field_article_body_summary', $new_summary);
      }
      else {
        $row->setSourceProperty('field_article_body_summary', $content[0]['summary']);
      }
    }
  }

}
