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

    $row->setSourceProperty('related_links', [
      [
        'url' => $row->getSourceProperty('field_dept_url')[0]['url'],
        'title' => $row->getSourceProperty('field_dept_url')[0]['title'],
      ],
      [
        'url' => $row->getSourceProperty('field_catalog_url')[0]['url'],
        'title' => $row->getSourceProperty('field_catalog_url')[0]['title'],
      ],
    ]);

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
