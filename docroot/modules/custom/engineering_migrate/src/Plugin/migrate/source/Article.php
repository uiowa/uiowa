<?php

namespace Drupal\engineering_migrate\Plugin\migrate\source;

use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_engineering_article",
 *  source_module = "engineering_migrate"
 * )
 */
class Article extends BaseNodeSource {

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
   * Term-to-term mapping for tags.
   *
   * @var array
   */
  protected $termMapping;

  /**
   * Reference-to-term mapping for tags.
   *
   * @var array
   */
  protected $refMapping;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->join('field_data_body', 'b', 'n.nid = b.entity_id');
    $query->leftJoin('field_data_field_image', 'image', 'nr.vid = image.revision_id');
    $query->leftJoin('field_data_field_author', 'author', 'n.nid = author.entity_id');
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
        'field_image_fid',
        'field_image_alt',
        'field_image_title',
        'field_image_width',
        'field_image_height',
      ])
      ->fields('author', [
        'field_author_value',
      ])
      ->orderBy('nid');
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
    $this->clearMemory();

    // Process image field if it exists.
    $this->processImageField($row, 'field_image');
    // Search for D7 inline embeds and replace with D8 inline entities.
    $content = $row->getSourceProperty('body_value');

    // Replace any inline images, if they exist.
    $content = $this->replaceInlineImages($content, '/sites/www.engineering.uiowa.edu/files/');
    $content = $this->replaceInlineFiles($content);
    // Remove excess style markup.
    $content = preg_replace("#<style type=\"text/css\">(.|\n)*?</style>#", '', $content);
    $row->setSourceProperty('body_value', $content);

    // Strip tags so they don't show up in the field teaser.
    $row->setSourceProperty('body_summary', strip_tags($row->getSourceProperty('body_summary')));

    // Get the various references and terms
    // that will be combined into the Tags field..
    $tables = [
      'field_data_field_primary_department' => ['field_primary_department_target_id'],
      'field_data_field_department' => ['field_department_target_id'],
      'field_data_field_audience_multi' => ['field_audience_multi_target_id'],
      'field_data_field_tags' => ['field_tags_tid'],
    ];

    $this->fetchAdditionalFields($row, $tables);
    $this->getTags($row);

    // Minor adjustments to keep formatting consistent.
    $author = $row->getSourceProperty('field_author_value');
    $author = preg_replace('|by:?\s|i', '', $author);
    $row->setSourceProperty('field_author_value', $author);

    $this->fetchUrlAliases($row);

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

  /**
   * Map taxonomy to a tag.
   */
  protected function getTags(&$row) {
    $tids = $row->getSourceProperty('field_tags_tid');
    $target_ids = [
      'Primary Department: ' => $row->getSourceProperty('field_primary_department_target_id'),
      'Department Feed: ' => $row->getSourceProperty('field_department_target_id'),
      'Audience: ' => $row->getSourceProperty('field_audience_multi_target_id'),
    ];

    foreach ($tids as $tid) {
      if (isset($this->termMapping[$tid])) {
        $new_tids[] = $this->termMapping[$tid];
      }
      else {
        $source_tids[] = $tid;
      }
    }
    if (!empty($source_tids)) {
      $source_query = $this->select('taxonomy_term_data', 't');
      $source_query = $source_query->fields('t', [
        'tid',
        'name',
        // We can leave out description, as all are empty.
      ])
        ->condition('t.tid', $source_tids, 'in');
      $terms = $source_query->distinct()
        ->execute()
        ->fetchAllKeyed(0, 1);
      foreach ($terms as $tid => $name) {
        $term = Term::create([
          'name' => $name,
          'vid' => 'tags',
        ]);
        if ($term->save()) {
          $this->termMapping[$tid] = $term->id();
          $new_tids[] = $term->id();
        }
      }
    }

    // Now for the reference ids.
    foreach ($target_ids as $type => $ref_ids) {
      foreach ($ref_ids as $ref_id) {
        if (isset($this->refMapping[$ref_id])) {
          $new_tids[] = $this->refMapping[$ref_id];
        }
        else {
          $source_ref_ids[] = $ref_id;
        }
      }
      if (!empty($source_ref_ids)) {
        $source_query = $this->select('taxonomy_term_data', 't');
        $source_query = $source_query->fields('t', [
          'tid',
          'name',
          // We can leave out description, as all are empty.
        ])
          ->condition('t.tid', $source_ref_ids, 'in');
        $terms = $source_query->distinct()
          ->execute()
          ->fetchAllKeyed(0, 1);
        foreach ($terms as $ref_id => $name) {
          // Prepend the fieldtype and create a new term.
          $term = Term::create([
            'name' => $type . $name,
            'vid' => 'tags',
          ]);
          if ($term->save()) {
            $this->refMapping[$ref_id] = $term->id();
            $new_tids[] = $term->id();
          }
        }
      }
    }

    if (!empty($new_tids)) {
      $row->setSourceProperty('article_tids', $new_tids);
    }
  }

}
