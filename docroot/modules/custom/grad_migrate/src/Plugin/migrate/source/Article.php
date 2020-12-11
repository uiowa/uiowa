<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_grad_article",
 *  source_module = "grad_migrate"
 * )
 */
class Article extends BaseNodeSource {

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
    $query = parent::query();
    $query->join('field_data_body', 'b', 'n.nid = b.entity_id');
    $query->leftJoin('field_data_field_header_image', 'hi', 'n.nid = hi.entity_id');
    $query->leftJoin('field_data_field_thumbnail_image', 'ti', 'n.nid = ti.entity_id');
    $query->leftJoin('field_data_field_lead', 'l', 'n.nid = l.entity_id');
    $query->leftJoin('field_data_field_pull_quote', 'pq', 'n.nid = pq.entity_id');
    $query->leftJoin('field_data_field_pull_quote_featured', 'pqf', 'n.nid = pqf.entity_id');
    $query->leftJoin('field_data_field_annual_report', 'ar', 'n.nid = ar.entity_id');
    // Both field_data_field_article_source_link table is empty and not needed
    // and field_data_field_attachments.
    $query->leftJoin('field_data_field_photo_credit', 'pc', 'n.nid = pc.entity_id');
    $query = $query->fields('b', [
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
    ])
      // @todo Join article author reference.
      // @todo Join article people reference.
      // @todo Join tags reference.
      // @todo Join programs reference.
      // @todo Join editorial group reference.
      // @todo Check D8 migration status.
      ->fields('hi', [
        'field_header_image_fid',
        'field_header_image_alt',
        'field_header_image_title',
        'field_header_image_width',
        'field_header_image_height',
      ])
      ->fields('ti', [
        'field_thumbnail_image_fid',
        'field_thumbnail_image_alt',
        'field_thumbnail_image_title',
        'field_thumbnail_image_width',
        'field_thumbnail_image_height',
      ])
      ->fields('l', [
        'field_lead_value',
        'field_lead_format',
      ])
      ->fields('pq', [
        'field_pull_quote_value',
        'field_pull_quote_format',
      ])
      ->fields('pqf', [
        'field_pull_quote_featured_value',
      ])
      ->fields('ar', [
        'field_annual_report_value',
      ])
      ->fields('pc', [
        'field_photo_credit_value',
        'field_photo_credit_format',
      ])
      ->fields('n', [
        'title',
        'created',
        'changed',
        'status',
        'promote',
        'sticky',
      ]);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'entity_type' => $this->t('(article body) Entity type body content is associated with'),
      'bundle' => $this->t('(article body) Bundle the node associated to the body content belongs to'),
      'deleted' => $this->t('(article body) Indicator for content marked for deletion'),
      'entity_id' => $this->t('(article body) ID of the entity the body content is associated with'),
      'revision_id' => $this->t('(article body) Revision ID for the piece of content'),
      'language' => $this->t('(article body) Language designation'),
      'delta' => $this->t('(article body) 0 for standard sites'),
      'body_value' => $this->t('(article body) Body content'),
      'body_summary' => $this->t('(article body) Body summary content'),
      'body_format' => $this->t('(article body) Body content text format'),
      'title' => $this->t('(node) Node title'),
      'created' => $this->t('(node) Timestamp for node creation date'),
      'changed' => $this->t('(node) Timestamp for node last changed date'),
      'status' => $this->t('(node) 0/1 for Unpublished/Published'),
      'promote' => $this->t('(node) 0/1 for Unpromoted/Promoted'),
      'sticky' => $this->t('(node) 0/1 for Unsticky/Sticky'),
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
   * Prepare row used for altering source data prior to its insertion.
   */
  public function prepareRow(Row $row) {

    // Check if an image was attached, and if so, update with new fid.
    $original_fid = $row->getSourceProperty('field_header_image_fid');
    if (isset($original_fid)) {
      $row->setSourceProperty('field_header_image_fid', $this->getFid($original_fid, 'migrate_map_d7__grad_file'));
    }

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
