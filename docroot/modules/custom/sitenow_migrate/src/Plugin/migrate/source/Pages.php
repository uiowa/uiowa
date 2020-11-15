<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "pages",
 *  source_module = "sitenow_migrate"
 * )
 */
class Pages extends BaseNodeSource {

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
    // Search for D7 inline embeds and replace with D8 inline entities.
    $content = $row->getSourceProperty('body_value');
    $content = preg_replace_callback("|\[\[\{.*?\"fid\":\"(.*?)\".*?\]\]|", [
      $this,
      'entityReplace',
    ], $content);
    $row->setSourceProperty('body_value', $content);

    // Check summary, and create one if none exists.
    if (!$row->getSourceProperty('body_summary')) {
      $new_summary = $this->extractSummaryFromText($content);
      $row->setSourceProperty('body_summary', $new_summary);
    }
    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
