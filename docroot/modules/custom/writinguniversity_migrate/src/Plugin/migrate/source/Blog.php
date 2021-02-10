<?php

namespace Drupal\writinguniversity_migrate\Plugin\migrate\source;

use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_writinguniversity_blog",
 *  source_module = "writinguniversity_migrate"
 * )
 */
class Blog extends BaseNodeSource {

  use ProcessMediaTrait;

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
    $query->leftJoin('field_data_field_large_featured_blog_image', 'image', 'n.nid = image.entity_id');
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
      ->fields('image', [
        'field_large_featured_blog_image_fid',
        'field_large_featured_blog_image_alt',
        'field_large_featured_blog_image_title',
        'field_large_featured_blog_image_width',
        'field_large_featured_blog_image_height',
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
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function prepareRow(Row $row) {
    // Process image field if it exists.
    $this->processImageField($row, 'field_large_featured_blog_image');

    // Search for D7 inline embeds and replace with D8 inline entities.
    $content = $row->getSourceProperty('body_value');

    // Replace any inline images, if they exist.
    $content = $this->replaceInlineImages($content, '/sites/vwu/files/');

    $row->setSourceProperty('body_value', $content);

    // Strip tags so they don't show up in the field teaser.
    $row->setSourceProperty('body_summary', strip_tags($row->getSourceProperty('body_summary')));

    $this->fetchUrlAliases($row);

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
