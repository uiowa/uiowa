<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "pages",
 *  source_module = "sitenow_migrate"
 * )
 */
class Pages extends SqlBase {

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
    $query = $this->select('node', 'n');
    $query->join('field_data_body', 'b', 'n.nid = b.entity_id');
    $query = $query->fields('n', [
      'nid',
      'vid',
      'type',
      'language',
      'title',
      'uid',
      'status',
      'created',
      'changed',
      'comment',
      'promote',
      'sticky',
      'tnid',
      'translate',
    ])
      ->fields('b', [
        'body_value',
        'body_summary',
        'body_format',
      ])
      ->condition('n.type', 'page');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
      'vid' => $this->t('Node revision ID'),
      'language' => $this->t('Language'),
      'title' => $this->t('Node Title'),
      'uid' => $this->t('User ID of node author'),
      'status' => $this->t('Published/unpublished'),
      'created' => $this->t('Timestamp of creation'),
      'changed' => $this->t('Timestamp of last change'),
      'comment' => $this->t('Comments enabled/disabled'),
      'promote' => $this->t('Promoted'),
      'sticky' => $this->t('Stickied'),
      'tnid' => $this->t('Translation ID'),
      'translate' => $this->t('Page being translated?'),
      'body_value' => $this->t('The actual body text being migrated'),
      'body_summary' => $this->t("The page's summary test"),
      'body_format' => $this->t("The body text field's formatting"),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
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

    // Search for D7 inline embeds and replace with D8 inline entities.
    $content = $row->getSourceProperty('body_value');
    $content = preg_replace_callback("|\[\[\{.*?\"fid\":\"(.*?)\".*?\]\]|", [
      $this,
      'entityReplace',
    ], $content);
    $row->setSourceProperty('body_value', $content);

    // Check summary, and create one if none exists.
    if (!$row->getSourceProperty('body_summary')) {
      $new_summary = substr($content, 0, 200);
      // Shorten the string until we reach a natural(ish) breaking point.
      $looper = TRUE;
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
      $row->setSourceProperty('body_summary', $new_summary);
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

}
