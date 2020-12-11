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
    $query->leftJoin('field_data_field_thesis_location', 'l', 'n.nid = l.entity_id');
    $query->leftJoin('field_data_field_thesis_defense_date', 'd', 'n.nid = d.entity_id');
    $query->leftJoin('field_data_field_thesis_firstname', 'fn', 'n.nid = fn.entity_id');
    $query->leftJoin('field_data_field_thesis_lastname', 'ln', 'n.nid = ln.entity_id');
    $query->leftJoin('field_data_field_thesis_title', 'tt', 'n.nid = tt.entity_id');
    $query->leftJoin('field_data_field_thesis_department', 'td', 'n.nid = td.entity_id');
    $query->leftJoin('field_data_field_thesis_chair', 'tc', 'n.nid = tc.entity_id');
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
        'field_thesis_first_name_value',
        'field_thesis_first_name_format',
      ])
      ->fields('ln', [
        'field_thesis_last_name_value',
        'field_thesis_last_name_format',
      ])
      ->fields('tt', [
        'field_thesis_title_value',
        'field_thesis_title_format',
      ])
      ->fields('td', [
        'field_thesis_department_value',
      ])
      ->fields('tc', [
        'field_thesis_chair_value',
        'field_thesis_chair_format',
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

}
