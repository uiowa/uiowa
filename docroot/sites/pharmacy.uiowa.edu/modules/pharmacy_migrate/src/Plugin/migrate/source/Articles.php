<?php
namespace Drupal\pharmacy_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "pharmacy_migrate_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    //@todo: Unlink anchors in body from articles before 2016.
    return TRUE;
  }

}
