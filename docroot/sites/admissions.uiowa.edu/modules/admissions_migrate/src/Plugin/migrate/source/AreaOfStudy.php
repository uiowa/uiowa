<?php

namespace Drupal\admissions_migrate\Plugin\migrate\source;

use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_admissions_aos",
 *  source_module = "node"
 * )
 */
class AreaOfStudy extends BaseNodeSource {

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
   * Node-to-node mapping for author content.
   *
   * @var array
   */
  protected $authorMapping;

  /**
   * Term-to-term mapping for tags.
   *
   * @var array
   */
  protected $termMapping;

  /**
   * Prepare row used for altering source data prior to its insertion.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');

    // Get Field API field values.
    foreach ($this->getFields('node', 'undergraduate_majors_programs') as $field_name => $field) {
      $row->setSourceProperty($field_name, $this->getFieldValues('node', $field_name, $nid));
    }

    $related_links = [];

    if ($dept_url = $row->getSourceProperty('field_dept_url')) {
      $related_links[] = [
        'url' => $dept_url[0]['url'],
        'title' => $dept_url[0]['title'],
      ];
    }

    if ($catalog_url = $row->getSourceProperty('field_catalog_url')) {
      $related_links[] = [
        'url' => $catalog_url[0]['url'],
        'title' => $catalog_url[0]['title'],
      ];
    }

    $row->setSourceProperty('related_links', $related_links);

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
