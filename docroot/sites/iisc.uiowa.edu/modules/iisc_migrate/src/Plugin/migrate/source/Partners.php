<?php

namespace Drupal\iisc_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "iisc_partners",
 *   source_module = "node"
 * )
 */
class Partners extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  protected $multiValueFields = [
    'field_ref_ia_counties' => 'target_id',
  ];

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // ID's for counties are off by -1, so just make that adjustment.
    if ($counties = $row->getSourceProperty('field_ref_ia_counties_target_id')) {
      foreach ($counties as $k => $target_id) {
        $counties[$k] = $target_id - 1;
      }
      $row->setSourceProperty('counties', $counties);
      $counties = NULL;
    }
    return TRUE;
  }

}
