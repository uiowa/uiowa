<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_grad_thesis_defense",
 *  source_module = "grad_migrate"
 * )
 */
class ThesisDefense extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // @todo Add any additional fields that need to be defined.
    // @todo field_thesis_firstname
    // @todo field_thesis_lastname
    // @todo field_thesis_defense_date
    // @todo field_thesis_location
    // @todo field_thesis_title
    // @todo field_thesis_department
    // @todo field_thesis_chair
    // @todo upload
    return $query;
  }

}
