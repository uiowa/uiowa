<?php

namespace Drupal\admissions_migrate\Plugin\migrate\source;

use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_admissions_aos",
 *  source_module = "admissions_migrate"
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
    // Strip tags so they don't show up in the field teaser.
    $row->setSourceProperty('body_summary', strip_tags($row->getSourceProperty('body_summary')));

    // Grab the various multi-value fields.
    $multivalue_fields = [
      'field_data_field_minor' => ['field_minor_value'],
      'field_data_field_certificates' => ['field_certificates_value'],
      'field_data_field_preprofessional' => ['field_preprofessional_value'],
      'field_data_field_online' => ['field_online_value'],
    ];

    $this->fetchAdditionalFields($row, $multivalue_fields);

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
