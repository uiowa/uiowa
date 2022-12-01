<?php

namespace Drupal\iisc_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "iisc_projects",
 *   source_module = "node"
 * )
 */
class Projects extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Process the body field for embedded media.
    $body = $row->getSourceProperty('body');
    if (!empty($body)) {
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceRelLinkedFiles($body[0]['value']);
      $row->setSourceProperty('body', $body);
    }
    $body = NULL;

    // Process academic years from term to select list.
    if ($years = $row->getSourceProperty('field_ref_academic_year_target_id')) {
      foreach ($years as $delta => $target_id) {
        $years[$delta] = $this->mapAcademicYearTargetIdToValue($target_id);
      }
      $row->setSourceProperty('field_ref_academic_year_target_id', $years);
      $years = NULL;
    }

    return TRUE;
  }

  /**
   * Map Academic Year term IDs to select list values.
   */
  private function mapAcademicYearTargetIdToValue($target_id) {
    $map = [
      100 => 2009,
      101 => 2010,
      102 => 2011,
      103 => 2012,
      104 => 2013,
      105 => 2014,
      106 => 2015,
      107 => 2016,
      176 => 2017,
      431 => 2018,
      436 => 2019,
      471 => 2020,
      476 => 2021,
    ];

    return $map[$target_id] ?? NULL;
  }

}
