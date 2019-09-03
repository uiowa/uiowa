<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "articles",
 *  source_module = "sitenow_migrate"
 * )
 */
class Articles extends SqlBase {

  /**
   * The public file directory path.
   *
   * @var string
   */
  protected $publicPath;

  /**
   * The private file directory path, if any.
   *
   * @var string
   */
  protected $privatePath;

  /**
   * The temporary file directory path.
   *
   * @var string
   */
  protected $temporaryPath;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('field_revision_field_article_body', 'b');
    $query->join('node', 'n', 'n.nid = b.entity_id');
    $query = $query->fields('b', [
      'entity_type',
      'bundle',
      'deleted',
      'entity_id',
      'revision_id',
      'language',
      'delta',
      'field_article_body_value',
      'field_article_body_summary',
      'field_article_body_format',
    ])
      ->fields('n', [
        'title',
        'created',
        'changed',
        'status',
        'promote',
        'sticky',
      ])
      ->condition('b.bundle', 'article');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'entity_type' => $this->t('Entity the body is attached to. Typically "node"'),
      'bundle' => $this->t('Entity bundle'),
      'deleted' => $this->t('0/1 if marked for deletion'),
      'entity_id' => $this->t('Entity ID body is attached to'),
      'revision_id' => $this->t('Revision ID for this content'),
      'language' => $this->t('Language for this field'),
      'delta' => $this->t('Field delta'),
      'field_article_body_value' => $this->t('Body content'),
      'field_article_body_summary' => $this->t('Body:summary content'),
      'field_article_body_format' => $this->t('Body:format selection'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'entity_id' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
    ];
  }

  /**
   * Prepare Row used for altering source data prior to its insertion into the destination.
   */
  public function prepareRow(Row $row) {
    // Can do extra preparation work here.
    return parent::prepareRow($row);
  }

}
