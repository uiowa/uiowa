<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
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
  use LinkReplaceTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Check if an image was attached, and if so, update with new fid.
    $image = $row->getSourceProperty('field_image');

    if (!empty($image)) {
      $row->setSourceProperty('field_image_fid', $this->getFid($image[0]['fid']));
    }

    // Search for D7 inline embeds and replace with D8 inline entities.
    $article_body = $row->getSourceProperty('field_article_body');

    if (!empty($article_body)) {
      $article_body[0]['value'] = $this->replaceInlineFiles($article_body[0]['value']);
      $row->setSourceProperty('field_article_body', $article_body);

      // Check summary, and create one if none exists.
      if (empty($row->getSourceProperty('field_article_body_summary'))) {
        $row->setSourceProperty('field_article_body_summary', $this->getSummaryFromTextField($article_body));
      }
    }

    return TRUE;
  }

  /**
   * Functions to run following a completed migration.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migration event.
   */
  public function postImport(MigrateImportEvent $event) {
    static $have_run = FALSE;

    if (!$have_run) {
      $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
      $have_run = TRUE;
    }
  }

}
