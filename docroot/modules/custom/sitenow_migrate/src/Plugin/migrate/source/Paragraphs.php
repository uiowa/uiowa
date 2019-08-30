<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "basic_pages",
 *  source_module = "sitenow_migrate"
 * )
 */
class Paragraphs extends SqlBase {

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
    $query = $this->select('field_revision_body', 'b')
      ->fields('b', [
        'entity_type',
        'bundle',
        'deleted',
        'entity_id',
        'revision_id',
        'language',
        'delta',
        'body_value',
        'body_summary',
        'body_format',
      ]);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
      'vid' => $this->t('Node revision ID'),
      'language' => $this->t('Language'),
      'title' => $this->t('Node Title'),
      'uid' => $this->t('User ID of node author'),
      'status' => $this->t('Published/unpublished'),
      'created' => $this->t('Timestamp of creation'),
      'changed' => $this->t('Timestamp of last change'),
      'comment' => $this->t('Comments enabled/disabled'),
      'promote' => $this->t('Promoted'),
      'sticky' => $this->t('Stickied'),
      'tnid' => $this->t('Translation ID'),
      'translate' => $this->t('Page being translated?'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
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
