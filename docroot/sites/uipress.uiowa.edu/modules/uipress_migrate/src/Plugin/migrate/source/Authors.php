<?php

namespace Drupal\uipress_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "uipress_authors",
 *   source_module = "node"
 * )
 */
class Authors extends BaseNodeSource {
  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
    $query->fields('alias', ['alias']);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['alias'] = $this->t('The URL alias for this node.');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);
    // If there's a suffix, append it to the last name field.
    if ($suffix = $row->getSourceProperty('field_author_suffix')) {
      $lastname = $row->getSourceProperty('field_author_lastname');
      $lastname[0]['value'] .= ', ' . $suffix[0]['value'];
      // @todo Make sure last name's with apostrophes don't get saved with
      //   the encoding in place of the apostrophe.
      $row->setSourceProperty('field_author_lastname', $lastname);
    }
    return TRUE;
  }

}
