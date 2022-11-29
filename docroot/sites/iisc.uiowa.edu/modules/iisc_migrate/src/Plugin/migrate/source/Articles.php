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
    if (!is_null($row->getSourceProperty('field_article_type'))) {
      if ($row->getSourceProperty('field_article_type')[0]['value'] === '2_ianow') {

        // Set article source to 'Iowa Now'.
        if (!is_null($row->getSourceProperty('field_article_publication_source'))) {
          $row->setSourceProperty('field_article_publication_source', [
            ['value' => 'Iowa Now'],
          ]);
        }

        // Set external URL to Iowa Now URL.
        if (!is_null($row->getSourceProperty('field_article_external_url')) && !is_null($row->getSourceProperty('field_article_iowanow_url'))) {
          $row->setSourceProperty('field_article_external_url', $row->getSourceProperty('field_article_iowanow_url'));
        }

        // @todo Need to set link directly to source if there is an external URL.
      }
    }

    $related_content = [];
    foreach ([
      'field_ref_partners',
      'field_ref_projects',
      'field_ref_persons',
    ] as $related_field) {
      if (!is_null($row->getSourceProperty($related_field))) {
        foreach ($row->getSourceProperty($related_field) as $field) {
          $related_content[] = $field['target_id'];
        }
      }
    }
    $row->setSourceProperty('field_related_content', $related_content);
    $related_content = NULL;

    return TRUE;
  }

}
