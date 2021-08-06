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
   * Term-to-name mapping for authors.
   *
   * @var array
   */
  protected $termMapping;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Select node in its last revision.
    $query = $this->select('taxonomy_term_data', 't')
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
        'field_physics_itu_taxonomy_category_tid',
      ])
      ->condition('t.vid', 11, '=');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // @todo Use the author mapping code to map the category data.
    $author_tids = $row->getSourceProperty('field_news_author_tid');
    if (!empty($author_tids)) {
      $authors = [];
      foreach ($author_tids as $tid) {
        if (!isset($this->termMapping[$tid])) {
          $source_query = $this->select('taxonomy_term_data', 't');
          $source_query = $source_query->fields('t', ['name'])
            ->condition('t.tid', $tid, '=');
          $this->termMapping[$tid] = $source_query->execute()->fetchCol()[0];
        }
        $authors[] = $this->termMapping[$tid];
      }
      $source_org_text = implode(', ', $authors);
      $row->setSourceProperty('field_news_authors', $source_org_text);
    }

    // @todo Update the fields here to handle the taxonomy body field instead.
    $body = $row->getSourceProperty('body');

    if (!empty($body)) {
      // Search for D7 inline embeds and replace with D8 inline entities.
      $this->viewMode = 'large__no_crop';
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    return TRUE;
  }

}
