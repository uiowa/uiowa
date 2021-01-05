<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
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
    $query->leftJoin('field_data_field_thesis_location', 'l', 'n.nid = l.entity_id');
    $query->leftJoin('field_data_field_thesis_defense_date', 'd', 'n.nid = d.entity_id');
    $query->leftJoin('field_data_field_thesis_firstname', 'fn', 'n.nid = fn.entity_id');
    $query->leftJoin('field_data_field_thesis_lastname', 'ln', 'n.nid = ln.entity_id');
    $query->leftJoin('field_data_field_thesis_title', 'tt', 'n.nid = tt.entity_id');
    $query->leftJoin('field_data_field_thesis_department', 'td', 'n.nid = td.entity_id');
    $query->leftJoin('field_data_upload', 'u', 'n.nid = u.entity_id');
    $query->leftJoin('field_data_field_d8_migration_status', 's', 'n.nid = s.entity_id');

    $query = $query->fields('n', [
      'title',
      'created',
      'changed',
      'status',
      'promote',
      'sticky',
    ])
      ->fields('l', [
        'field_thesis_location_value',
        'field_thesis_location_format',
      ])
      ->fields('d', [
        'field_thesis_defense_date_value',
      ])
      ->fields('fn', [
        'field_thesis_firstname_value',
        'field_thesis_firstname_format',
      ])
      ->fields('ln', [
        'field_thesis_lastname_value',
        'field_thesis_lastname_format',
      ])
      ->fields('tt', [
        'field_thesis_title_value',
        'field_thesis_title_format',
      ])
      ->fields('td', [
        'field_thesis_department_value',
      ])
      ->fields('u', [
        'delta',
        'upload_fid',
        'upload_display',
        'upload_description',
      ])
      ->fields('s', [
        'field_d8_migration_status_value',
      ]);
    return $query;
  }

  /**
   * Prepare row used for altering source data prior to its insertion.
   */
  public function prepareRow(Row $row) {
    // Get our multi-value fields.
    $additional_fields = [
      'field_data_field_thesis_chair' => [
        'field_thesis_chair_value',
      ],
    ];
    $this->fetchAdditionalFields($row, $additional_fields);

    // Grab the mapped FID for the file upload field..
    $original_fid = $row->getSourceProperty('upload_fid');
    if (isset($original_fid)) {
      $row->setSourceProperty('upload_fid', $this->getFid($original_fid, 'migrate_map_d7_grad_file'));
    }

    $old_date_format = $row->getSourceProperty('field_thesis_defense_date_value');
    if (isset($old_date_format)) {
      // There's an extra formatter 'T' that can be removed
      // or handled by the createFromFormat.
      // In this case, we should be able to remove it.
      $old_date_format = str_replace('T', ' ', $old_date_format);
      $row->setSourceProperty('field_thesis_defense_date_value', \DateTime::createFromFormat('Y-m-d H:i:s', $old_date_format)->getTimestamp());
    }

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
