<?php

namespace Drupal\iwp_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "iwp_bio",
 *   source_module = "node"
 * )
 */
class Bio extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Make sure our nodes are retrieved in order
    // for ease of debugging.
    $query->orderBy('nid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    $body = $row->getSourceProperty('body');
    if (isset($body)) {
      $body[0]['format'] = 'filtered_html';
      $row->setSourceProperty('body', $body);
    }

    if ($image = $row->getSourceProperty('field_image_attach')) {
      $row->setSourceProperty('field_image_attach', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }

    if ($file = $row->getSourceProperty('field_writer_sample')) {
      $row->setSourceProperty('field_writer_sample', $this->processFileField($file[0]['fid']));
    }

    if ($file = $row->getSourceProperty('field_writing_sample_in_original')) {
      $row->setSourceProperty('field_writing_sample_in_original', $this->processFileField($file[0]['fid']));
    }

    return TRUE;
  }

}
