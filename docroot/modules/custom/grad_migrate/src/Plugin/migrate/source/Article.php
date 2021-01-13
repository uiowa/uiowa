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
    // field_data_field_header_image is not used.
    $query->leftJoin('field_data_field_thumbnail_image', 'ti', 'n.nid = ti.entity_id');
    $query->leftJoin('field_data_field_lead', 'l', 'n.nid = l.entity_id');
    $query->leftJoin('field_data_field_pull_quote', 'pq', 'n.nid = pq.entity_id');
    $query->leftJoin('field_data_field_pull_quote_featured', 'pqf', 'n.nid = pqf.entity_id');
    // field_data_field_annual_report is not needed.
    // field_data_field_article_source_link is not needed.
    // field_data_field_attachments is not needed.
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
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function prepareRow(Row $row) {

    // Hard-coding our constants, at least for now.
    $source_base_path = 'https://www.grad.uiowa.edu/sites/gc/files/';
    // Put the files in a time-specified path to match
    // default file upload behaviors.
    $drupal_file_directory = 'public://' . date('Y-m') . '/';

    // Check if an image was attached, and if so, update with new fid.
    $original_fid = $row->getSourceProperty('field_thumbnail_image_fid');
    if (isset($original_fid)) {
      $filename = $this->fidQuery($original_fid)['filename'];
      // Get a connection for the destination database.
      $dest_connection = \Drupal::database();
      $dest_query = $dest_connection->select('file_managed', 'f');
      $new_fid = $dest_query->fields('f', ['fid'])
        ->condition('f.filename', $filename)
        ->execute()
        ->fetchField();
      if (!$new_fid) {
        $new_fid = $this->downloadFile($filename, $source_base_path, $drupal_file_directory);
        $meta['alt'] = $row->getSourceProperty('field_thumbnail_image_alt');
        $meta['title'] = $row->getSourceProperty('field_thumbnail_image_title');
        $mid = $this->createMediaEntity($new_fid, $meta);
      }
      else {
        $mid = $this->getMid($filename);
        // And in case we had the file, but not the media entity.
        if (!$mid) {
          $meta['alt'] = $row->getSourceProperty('field_thumbnail_image_alt');
          $meta['title'] = $row->getSourceProperty('field_thumbnail_image_title');
          $mid = $this->createMediaEntity($new_fid, $meta);
        }
      }
      $row->setSourceProperty('field_thumbnail_image_fid', $mid);
    }

    // Search for D7 inline embeds and replace with D8 inline entities.
    $content = $row->getSourceProperty('body_value');
    // @todo clean this up so we don't run two separate regexps.
    // Check for any inline file references
    // and make sure we have files ready to be referenced.
    preg_match_all("|\[\[\{.*?\"fid\":\"(.*?)\".*?\]\]|", $content, $matches);
    foreach ($matches[1] as $original_fid) {
      $filename = $this->fidQuery($original_fid)['filename'];
      $dest_connection = isset($dest_connection) ? $dest_connection : \Drupal::database();
      $dest_query = $dest_connection->select('file_managed', 'f');
      $new_fid = $dest_query->fields('f', ['fid'])
        ->condition('f.filename', $filename)
        ->execute()
        ->fetchField();
      // If we didn't find the file, we need to download it
      // and create it's media entity.
      if (!$new_fid) {
        $new_fid = $this->downloadFile($filename, $source_base_path, $drupal_file_directory);
        // Fetch old meta.
        $query = $this->select('file_managed', 'f');
        $query->join('field_data_field_file_image_alt_text', 'a', 'a.entity_id = f.fid');
        $query->join('field_data_field_file_image_title_text', 't', 't.entity_id = f.fid');
        $result = $query->fields('a', [
          'field_file_image_alt_text_value',
        ])
          ->fields('t', [
            'field_file_image_title_text_value',
          ])
          ->condition('f.fid', $original_fid)
          ->execute()
          ->fetchAssoc();
        $meta['alt'] = $result['field_file_image_alt_text_value'];
        $meta['title'] = $result['field_file_image_title_text_value'];
        $this->createMediaEntity($new_fid, $meta);
      }
    }
    // We have the files, so now we're able to replace with new entities.
    $content = preg_replace_callback("|\[\[\{.*?\"fid\":\"(.*?)\".*?\]\]|", [
      $this,
      'entityReplace',
    ], $content);
    $row->setSourceProperty('body_value', $content);

    // Strip tags so they don't show up in the field teaser.
    $row->setSourceProperty('body_summary', strip_tags($row->getSourceProperty('body_summary')));

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
