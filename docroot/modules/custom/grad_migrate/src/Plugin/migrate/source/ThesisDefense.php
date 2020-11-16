<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "grad_thesis_defense",
 *  source_module = "grad_migrate"
 * )
 */
class ThesisDefense extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('field_data_body', 'b');
    $query->join('node', 'n', 'n.nid = b.entity_id');
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
      ->fields('n', [
        'title',
        'created',
        'changed',
        'status',
        'promote',
        'sticky',
      ])
      ->condition('b.bundle', 'thesis_defense');
    // @todo Add any additional fields that need to be defined.
    // @todo field_thesis_firstname
    // @todo field_thesis_lastname
    // @todo field_thesis_defense_date
    // @todo field_thesis_location
    // @todo field_thesis_title
    // @todo field_thesis_department
    // @todo field_thesis_chair
    // @todo upload
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
      'field_body_value' => $this->t('(article body) Body content'),
      'field_body_summary' => $this->t('(article body) Body summary content'),
      'field_body_format' => $this->t('(article body) Body content text format'),
      'title' => $this->t('(node) Node title'),
      'created' => $this->t('(node) Timestamp for node creation date'),
      'changed' => $this->t('(node) Timestamp for node last changed date'),
      'status' => $this->t('(node) 0/1 for Unpublished/Published'),
      'promote' => $this->t('(node) 0/1 for Unpromoted/Promoted'),
      'sticky' => $this->t('(node) 0/1 for Unsticky/Sticky'),
      // @todo Add any additional fields that need to be defined.
      // @todo field_thesis_firstname
      // @todo field_thesis_lastname
      // @todo field_thesis_defense_date
      // @todo field_thesis_location
      // @todo field_thesis_title
      // @todo field_thesis_department
      // @todo field_thesis_chair
      // @todo upload
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
