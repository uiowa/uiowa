<?php

namespace Drupal\iisc_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "iisc_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  protected $multiValueFields = [
    // @todo Add multivalue fields.
  ];

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // If article type is set to '2_ianow', we need to do some additional
    // processing.
    if ($row->getSourceProperty('field_article_type') === '2_ianow') {
      // Set article source to 'Iowa Now'.
      if (!is_null($row->getSourceProperty('field_article_publication_source'))) {
        $row->setSourceProperty('field_article_publication_source', 'Iowa Now');
      }

      // Set external URL to Iowa Now URL.
      if (!is_null($row->getSourceProperty('field_article_external_url')) && !is_null($row->getSourceProperty('field_article_iowanow_url'))) {
        $row->setSourceProperty('field_article_external_url', $row->getSourceProperty('field_article_iowanow_url'));
      }
    }

    return TRUE;
  }

}
