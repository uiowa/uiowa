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

  use ProcessGradMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('field_data_field_mentor_interest', 'interest', 'n.nid = interest.entity_id');
    // field_data_field_mentor_previously is not being migrated.
    // field_data_field_mentor_previously_years is not being migrated.
    $query->leftJoin('field_data_field_mentor_firstname', 'firstname', 'n.nid = firstname.entity_id');
    $query->leftJoin('field_data_field_mentor_lastname', 'lastname', 'n.nid = lastname.entity_id');
    $query->leftJoin('field_data_field_mentor_degrees', 'degrees', 'n.nid = degrees.entity_id');
    $query->leftJoin('field_data_field_mentor_position', 'position', 'n.nid = position.entity_id');
    $query->leftJoin('field_data_field_mentor_department', 'department', 'n.nid = department.entity_id');
    // field_data_field_mentor_secondary_dept is not being migrated.
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
      ])
      ->fields('degrees', [
        'field_mentor_degrees_value',
      ])
      ->fields('position', [
        'field_mentor_position_value',
      ])
      ->fields('email', [
        'field_mentor_email_email',
      ])
      ->fields('project_title', [
        'field_mentor_project_title_value',
      ])
      ->fields('phone', [
        'field_mentor_phone_phone_na',
      ])
      ->fields('website', [
        'field_mentor_website_url',
      ])
      ->fields('undergrad_role', [
        'field_project_undergrad_role_value',
      ])
      ->fields('undergrad_qualif', [
        'field_project_undergrad_qualif_value',
      ])
      ->fields('research_desc', [
        'field_project_research_desc_value',
      ]);
    return $query;
  }

  /**
   * Prepare row used for altering source data prior to its insertion.
   */
  public function prepareRow(Row $row) {
    // Update with protocols if missing.
    // Not a robust preprocess, but works for all data in this specific field.
    $url = $row->getSourceProperty('field_mentor_website_url');
    if (isset($url) && substr($url, 0, 4) != 'http') {
      $url = 'http://' . $url;
      $row->setSourceProperty('field_mentor_website_url', $url);
    }

    // Strip out HTML tags from project title.
    $row->setSourceProperty('field_mentor_project_title_value', strip_tags($row->getSourceProperty('field_mentor_project_title_value')));

    // Call the parent prepareRow.
    return parent::prepareRow($row);
  }

}
