<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "articles",
 *  source_module = "sitenow_migrate"
 * )
 */
class Articles extends BaseNodeSource {

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
    $query = $this->select('field_data_field_article_body', 'b');
    $query->join('node', 'n', 'n.nid = b.entity_id');
    $query->join('field_data_field_image', 'i', 'n.nid = i.entity_id');
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
      ->fields('i', [
        'field_image_fid',
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
      'entity_type' => $this->t('(article body) Entity type body content is associated with'),
      'bundle' => $this->t('(article body) Bundle the node associated to the body content belongs to'),
      'deleted' => $this->t('(article body) Indicator for content marked for deletion'),
      'entity_id' => $this->t('(article body) ID of the entity the body content is associated with'),
      'revision_id' => $this->t('(article body) Revision ID for the piece of content'),
      'language' => $this->t('(article body) Language designation'),
      'delta' => $this->t('(article body) 0 for standard sites'),
      'field_article_body_value' => $this->t('(article body) Body content'),
      'field_article_body_summary' => $this->t('(article body) Body summary content'),
      'field_article_body_format' => $this->t('(article body) Body content text format'),
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
