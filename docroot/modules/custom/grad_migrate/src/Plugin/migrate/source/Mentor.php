<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "d7_grad_srop_mentor",
 *  source_module = "grad_migrate"
 * )
 */
class Mentor extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('field_data_field_mentor_interest', 'interest', 'n.nid = interest.entity_id');
    $query->leftJoin('field_data_field_mentor_previously', 'previously', 'n.nid = previously.entity_id');
    $query->leftJoin('field_data_field_mentor_previously_years', 'previously_years', 'n.nid = previously_years.entity_id');
    $query->leftJoin('field_data_field_mentor_firstname', 'firstname', 'n.nid = firstname.entity_id');
    $query->leftJoin('field_data_field_mentor_lastname', 'lastname', 'n.nid = lastname.entity_id');
    $query->leftJoin('field_data_field_mentor_degrees', 'degrees', 'n.nid = degrees.entity_id');
    $query->leftJoin('field_data_field_mentor_position', 'position', 'n.nid = position.entity_id');
    $query->leftJoin('field_data_field_mentor_department', 'department', 'n.nid = department.entity_id');
    $query->leftJoin('field_data_field_mentor_secondary_dept', 'secondary_dept', 'n.nid = secondary_dept.entity_id');
    $query->leftJoin('field_data_field_mentor_college', 'college', 'n.nid = college.entity_id');
    $query->leftJoin('field_data_field_mentor_phone', 'phone', 'n.nid = phone.entity_id');
    $query->leftJoin('field_data_field_mentor_email', 'email', 'n.nid = email.entity_id');
    $query->leftJoin('field_data_field_mentor_website', 'website', 'n.nid = website.entity_id');
    $query->leftJoin('field_data_field_image_attach', 'image', 'n.nid = image.entity_id');
    $query->leftJoin('field_data_field_mentor_project_title', 'project_title', 'n.nid = project_title.entity_id');
    $query->leftJoin('field_data_field_project_assistants', 'assistants', 'n.nid = assistants.entity_id');
    $query->leftJoin('field_data_field_project_research_desc', 'research_desc', 'n.nid = research_desc.entity_id');
    $query->leftJoin('field_data_field_project_undergrad_role', 'undergrad_role', 'n.nid = undergrad_role.entity_id');
    $query->leftJoin('field_data_field_project_undergrad_qualif', 'undergrad_qualif', 'n.nid = undergrad_qualif.entity_id');

    $query = $query->fields('n', [
      'title',
      'created',
      'changed',
      'status',
      'promote',
      'sticky',
    ])
      ->fields('firstname', [
        'field_mentor_firstname_value',
        'field_mentor_firstname_format',
      ])
      ->fields('lastname', [
        'field_mentor_lastname_value',
        'field_mentor_lastname_format',
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
