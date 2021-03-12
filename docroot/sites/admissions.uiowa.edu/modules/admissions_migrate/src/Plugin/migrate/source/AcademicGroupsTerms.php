<?php

namespace Drupal\admissions_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Plugin\migrate\source\d7\Term;

/**
 * Taxonomy term source from database.
 *
 * @MigrateSource(
 *   id = "d7_admissions_academic_groups",
 *   source_module = "taxonomy"
 * )
 */
class AcademicGroupsTerms extends Term {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $this->processImageField($row, 'field_acad_group_primary_image');
    return parent::prepareRow($row);
  }

}
