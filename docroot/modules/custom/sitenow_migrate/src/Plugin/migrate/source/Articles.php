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
    $body = $row->getSourceProperty('field_article_body');

    if (!empty($body)) {
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);
      $row->setSourceProperty('field_article_body', $body);

      // Check summary, and create one if none exists.
      $row->setSourceProperty('field_article_body_summary', $this->getSummaryFromTextField($body));
    }

    return TRUE;
  }

  /**
   * Functions to run following a completed migration.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migration event.
   */
  public function postImportProcess(MigrateImportEvent $event) {
    $migration = $event->getMigration();
    $this->postLinkReplace($migration, 'field_article_body');
  }

}
