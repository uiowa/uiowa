<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_grad_srop_scholar",
 *  source_module = "grad_migrate"
 * )
 */
class Scholar extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('field_data_field_scholar_firstname', 'firstname', 'n.nid = firstname.entity_id');
    $query->leftJoin('field_data_field_scholar_middlename', 'middlename', 'n.nid = middlename.entity_id');
    $query->leftJoin('field_data_field_scholar_lastname', 'lastname', 'n.nid = lastname.entity_id');
    $query->leftJoin('field_data_field_scholar_institution', 'institution', 'n.nid = institution.entity_id');
    $query->leftJoin('field_data_field_scholar_mentorlink', 'mentorlink', 'n.nid = mentorlink.entity_id');
    $query->leftJoin('field_data_field_scholar_department', 'department', 'n.nid = department.entity_id');
    $query->leftJoin('field_data_field_scholar_sropyear', 'sropyear', 'n.nid = sropyear.entity_id');
    $query->leftJoin('field_data_field_scholar_project_title', 'project_title', 'n.nid = project_title.entity_id');
    $query->leftJoin('field_data_field_scholar_abstract', 'abstract', 'n.nid = abstract.entity_id');

    $query = $query->fields('n', [
      'title',
      'created',
      'changed',
      'status',
      'promote',
      'sticky',
    ])
      ->fields('firstname', [
        'field_scholar_firstname_value',
        'field_scholar_firstname_format',
      ])
      ->fields('middlename', [
        'field_scholar_middlename_value',
        'field_scholar_middlename_format',
      ])
      ->fields('lastname', [
        'field_scholar_lastname_value',
        'field_scholar_lastname_format',
      ])
      ->fields('institution', [
        'field_scholar_institution_value',
        'field_scholar_institution_format',
      ])
      ->fields('mentorlink', [
        'field_scholar_mentorlink_title',
        'field_scholar_mentorlink_url',
        'field_scholar_mentorlink_attributes',
      ])
      ->fields('department', [
        'field_scholar_department_value',
        'field_scholar_department_format',
      ])
      ->fields('sropyear', [
        'field_scholar_sropyear_value',
      ])
      ->fields('project_title', [
        'field_scholar_project_title_value',
        'field_scholar_project_title_format',
      ])
      ->fields('abstract', [
        'field_scholar_abstract_value',
        'field_scholar_abstract_format',
      ]);
    return $query;
  }

  /**
   * Prepare row used for altering source data prior to its insertion.
   */
  public function prepareRow(Row $row) {
    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
