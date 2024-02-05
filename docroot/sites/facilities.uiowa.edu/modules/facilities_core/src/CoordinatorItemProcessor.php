<?php

namespace Drupal\facilities_core;

use Drupal\uiowa_core\EntityItemProcessorBase;

/**
 * A processor for syncing coordinator content.
 */
class CoordinatorItemProcessor extends EntityItemProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldMap = [
    'field_b_coordinator_department' => 'mainDepartment',
    'field_b_coordinator_email' => 'mainCampusEmail',
    'field_b_coordinator_is_primary' => TRUE,
    'field_b_coordinator_job_title' => 'mainJobTitle',
    'field_b_coordinator_name' => 'mainFullName',
    'field_b_coordinator_phone_number' => 'mainCampusPhone',
  ];

}
