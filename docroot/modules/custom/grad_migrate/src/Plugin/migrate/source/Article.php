<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "grad_article",
 *  source_module = "grad_migrate"
 * )
 */
class Article extends BaseNodeSource {

  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('field_data_body', 'b');
    $query->join('node', 'n', 'n.nid = b.entity_id');
    $query->join('field_data_field_header_image', 'i', 'n.nid = i.entity_id');
    $query = $query->fields('b', [
      'entity_type',
      'bundle',
      'deleted',
      'entity_id',
      'revision_id',
      'language',
      'delta',
      'field_body_value',
      'field_body_summary',
      'field_body_format',
    ])
      ->fields('i', [
        'field_header_image_fid',
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
    // @todo Add any additional fields that need to be defined.
    // @todo field_author
    // @todo field_lead - necessary?
    // @todo field_thumbnail_image - necessary?
    // @todo field_pull_quote
    // @todo field_pull_quote_featured
    // @todo field_article_people
    // @todo field_article_program
    // @todo field_annual_report
    // @todo field_article_source_link
    // @todo field_photo_credit
    // @todo field_attachments
    // @todo field_tags
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'entity_type' => $this->t('Entity type of the content, should be node'),
      'bundle' => $this->t('(article body) Bundle the node associated to the body content belongs to'),
      'deleted' => $this->t('(article body) Indicator for content marked for deletion'),
      'entity_id' => $this->t('(article body) ID of the entity the body content is associated with'),
      'revision_id' => $this->t('(article body) Revision ID for the piece of content'),
      'language' => $this->t('(article body) Language designation'),
      'delta' => $this->t('(article body) 0 for standard sites'),
      'created' => $this->t('(node) Timestamp for node creation date'),
      'changed' => $this->t('(node) Timestamp for node last changed date'),
      'status' => $this->t('(node) 0/1 for Unpublished/Published'),
      'promote' => $this->t('(node) 0/1 for Unpromoted/Promoted'),
      'sticky' => $this->t('(node) 0/1 for Unsticky/Sticky'),
      'title' => $this->t('(node) Node title'),
      'field_body_value' => $this->t('(article body) Body content'),
      'field_body_summary' => $this->t('(article body) Body summary content'),
      'field_body_format' => $this->t('(article body) Body content text format'),
      'field_author' => $this->t('The author node reference.'),
      'field_lead' => $this->t('The lead field.'),
      'field_thumbnail_image' => $this->t('Thumbnail image field'),

      // @todo Add any additional fields that need to be defined.
      // @todo field_pull_quote
      // @todo field_pull_quote_featured
      // @todo field_article_people
      // @todo field_article_program
      // @todo field_annual_report
      // @todo field_article_source_link
      // @todo field_photo_credit
      // @todo field_attachments
      // @todo field_tags
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

}
