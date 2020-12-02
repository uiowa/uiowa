<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "files",
 *  source_module = "sitenow_migrate"
 * )
 */
class Files extends SqlBase {

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
    $query = $this->select('file_managed', 'f');
    // Limit it to only files which appear in the file_usage table.
    $query->innerJoin('file_usage', 'u', 'u.fid = f.fid');
    $query = $query->fields('f', [
        'fid',
        'filename',
        'uri',
        'filemime',
      ])
      // Need distinct to avoid duplicates from the file_usage join.
      ->distinct();
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'fid' => $this->t('File ID from D7'),
      'filename' => $this->t('Filename'),
      'uri' => $this->t('URI'),
      'filemime' => $this->t('Filemime'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'fid' => [
        'type' => 'integer',
        'alias' => 'f',
      ],
    ];
  }

  /**
   * Prepare row used for altering source data prior to its insertion.
   */
  public function prepareRow(Row $row) {
    // Create source filepath based on URI.
    $row->setSourceProperty('uri', str_replace("public://", "", $row->getSourceProperty('uri')));

    $fileType = explode('/', $row->getSourceProperty('filemime'))[0];
    if ($fileType == 'image') {
      $row->setSourceProperty('meta', $this->fetchMeta($row));
    }
    return parent::prepareRow($row);
  }

  /**
   * If the migrated file is an image, grab the alt and title text values.
   */
  public function fetchMeta($row) {
    $query = $this->select('file_managed', 'f');
    $query->join('field_data_field_file_image_alt_text', 'a', 'a.entity_id = f.fid');
    $query->join('field_data_field_file_image_title_text', 't', 't.entity_id = f.fid');
    $result = $query->fields('a', [
      'field_file_image_alt_text_value',
    ])
      ->fields('t', [
        'field_file_image_title_text_value',
      ])
      ->condition('f.fid', $row->getSourceProperty('fid'))
      ->execute();
    return $result->fetchAssoc();
  }

}
