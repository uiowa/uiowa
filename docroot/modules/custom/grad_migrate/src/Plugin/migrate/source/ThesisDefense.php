<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Plugin\migrate\source\d7\Node as D7Node;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "grad_thesis_defense",
 *  source_provider = "node"
 * )
 */
class ThesisDefense extends D7Node {

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
