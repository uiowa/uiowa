<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "articles",
 *  source_module = "sitenow_migrate"
 * )
 */
class Articles extends SqlBase {

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
    // Determine if the content should be published or not.
    switch ($row->getSourceProperty('status')) {

      case 1:
        $row->setSourceProperty('moderation_state', 'published');
        break;

      default:
        $row->setSourceProperty('moderation_state', 'draft');
    }

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
      $new_summary = substr($content, 0, 200);
      $looper = TRUE;
      // Shorten the string until we reach a natural(ish) breaking point.
      while ($looper && strlen($new_summary) > 0) {
        switch (substr($new_summary, -1)) {

          case '.':
          case '!':
          case '?':
            $looper = FALSE;
            break;

          case ';':
          case ':':
          case '"':
            $looper = FALSE;
            $new_summary = $new_summary . '...';
            break;

          default:
            $new_summary = substr($new_summary, 0, -1);
        }
      }
      // Strip out any HTML, and set the new summary.
      $new_summary = preg_replace("|<.*?>|", '', $new_summary);
      $row->setSourceProperty('field_article_body_summary', $new_summary);
    }
    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

  /**
   * Regex to find Drupal 7 JSON for inline embedded files.
   */
  public function entityReplace($match) {
    $fid = $match[1];
    $file_data = $this->fidQuery($fid);
    if ($file_data) {
      $uuid = $this->getMid($file_data['filename'])['uuid'];
      return $this->constructInlineEntity($uuid);
    }
    // Failed to find a file, so let's leave the content unchanged.
    return $match;
  }

  /**
   * Simple query to get info on the Drupal 7 file based on fid.
   */
  public function fidQuery($fid) {
    $query = $this->select('file_managed', 'f')
      ->fields('f', ['filename'])
      ->condition('f.fid', $fid);
    $results = $query->execute();
    return $results->fetchAssoc();
  }

  /**
   * Fetch the media uuid based on the provided filename.
   */
  public function getMid($filename) {
    $connection = \Drupal::database();
    $query = $connection->select('file_managed', 'f');
    $query->join('media__field_media_image', 'fmi', 'f.fid = fmi.field_media_image_target_id');
    $query->join('media', 'm', 'fmi.entity_id = m.mid');
    $result = $query->fields('m', ['uuid'])
      ->condition('f.filename', $filename)
      ->execute();
    return $result->fetchAssoc();
  }

  /**
   * Build the new inline embed entity format for Drupal 8 images.
   */
  public function constructInlineEntity($uuid) {
    $parts = [
      '<drupal-entity',
      'data-embed-button="media_entity_embed"',
      'data-entity-embed-display="view_mode:media.full"',
      'data-entity-embed-display-settings=""',
      'data-entity-type="media"',
      'data-entity-uuid="' . $uuid . '"',
      'data-langcode="en">',
      '</drupal-entity>',
    ];
    return implode(" ", $parts);
  }

  /**
   * Fetch the media id based on the original site's fid.
   */
  private function getFid($original_fid) {
    $connection = \Drupal::database();
    $query = $connection->select('migrate_map_d7_file', 'mm');
    $query->join('media__field_media_image', 'fmi', 'mm.destid1 = fmi.field_media_image_target_id');
    $result = $query->fields('fmi', ['entity_id'])
      ->condition('mm.sourceid1', $original_fid)
      ->execute();
    $new_fid = $result->fetchField();
    return $new_fid;
  }

}
