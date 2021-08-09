<?php

namespace Drupal\itu_physics_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "itu_physics_courses",
 *   source_module = "node"
 * )
 */
class Courses extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Select node in its last revision.
    $query = $this->select('taxonomy_term_data', 't');
    $query->leftJoin('field_data_field_physics_itu_taxonomy_body', 'b', 'b.entity_id = t.tid');
    $query->leftJoin('field_data_field_physics_itu_taxonomy_image', 'i', 'i.entity_id = t.tid');
    $query->leftJoin('field_data_field_physics_itu_category', 'c', 'c.entity_id = t.tid');
    $query = $query->fields('t', [
      'tid',
      'name',
      'description',
    ])
      ->fields('b', [
        'field_physics_itu_taxonomy_body_value',
      ])
      ->fields('i', [
        'field_physics_itu_taxonomy_image_fid',
        'field_physics_itu_taxonomy_image_alt',
        'field_physics_itu_taxonomy_image_title',
      ])
      ->fields('c', [
        'field_physics_itu_category_tid',
      ])
      ->condition('t.vid', 11, '=');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['tid']['type'] = 'integer';
    $ids['tid']['alias'] = 't';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchUrlAliases(Row &$row) {
    $tid = $row->getSourceProperty('tid');
    $row->setSourceProperty('alias', $this->select('url_alias', 'alias')
      ->fields('alias', ['alias'])
      ->condition('source', 'taxonomy/term/' . $tid, '=')
      ->execute()
      ->fetchCol());
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Set the moderation state to 'published' as default.
    $row->setSourceProperty('moderation_state', 'published');

    // @todo Do we need to map this category anywhere,
    //   or perhaps append it to the body?
    $category_tid = $row->getSourceProperty('field_physics_itu_category_tid');
    if (!empty($category_tid)) {
      $source_query = $this->select('taxonomy_term_data', 't');
      $source_query = $source_query->fields('t', ['name'])
        ->condition('t.tid', $category_tid, '=');
      $row->setSourceProperty('field_physics_itu_category_tid', $source_query->execute()->fetchCol()[0]);
    }
    // Process the image field and set it as a dest property.
    $row->setDestinationProperty('field_image', $this->processImageField(
      $row->getSourceProperty('field_physics_itu_taxonomy_image_fid'),
      $row->getSourceProperty('field_physics_itu_taxonomy_image_alt'),
      $row->getSourceProperty('field_physics_itu_taxonomy_image_title')
    ));

    $body_value = $row->getSourceProperty('field_physics_itu_taxonomy_body_value');
    $body = [];

    if (!empty($body_value)) {
      // Search for D7 inline embeds and replace with D8 inline entities.
      $this->viewMode = 'large__no_crop';
      $body['value'] = $this->replaceInlineFiles($body_value);
      $body['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);
    }

    return TRUE;
  }

}
