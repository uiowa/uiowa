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
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('file_managed', 'f')
      ->fields('f', [
        'fid',
        'filename',
        'uri',
        'filemime',
      ]);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'fid' => $this->t('fid'),
      'filename' => $this->t('filename'),
      'uri' => $this->t('uri'),
      'filemime' => $this->t('filemime'),
      'timestamp' => $this->t('timestamp'),
      'type' => $this->t('file type'),
      'created' => $this->t('created timestamp'),
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
   * Prepare Row used for altering source data prior to its insertion into the destination.
   */
  public function prepareRow(Row $row) {
    /* Can do extra preparation work here,
     * possibly including the media entity creation heavylifting.
     *
     * Do we want to do a file cleanup?
     * Can be done with the following.
     * file_delete($file->id());
     */
    $row->source_url = file_create_url($row->uri);
    return parent::prepareRow($row);
  }

}
