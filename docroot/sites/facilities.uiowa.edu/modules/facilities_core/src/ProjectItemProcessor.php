<?php

namespace Drupal\facilities_core;

use Drupal\uiowa_core\EntityItemProcessorBase;

/**
 * A processor for syncing building nodes.
 */
class ProjectItemProcessor extends EntityItemProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldMap = [
    'title' => 'projectTitle',
    'field_project_number' => 'buiProjectId',
    'field_project_type' => 'projectType',
    'field_project_building' => 'projectBuilding',
    'field_project_building_alt' => 'buildingName',
    'field_project_description' => 'projectScope',
    'field_project_status' => 'projectStatus',
    'field_project_square_footage' => 'grossSqFeet',
    'field_project_pre_bid_location' => 'preBidLocation',
    'field_project_awarded_to' => 'vendorName',
    'field_project_architect' => 'primaryConsultant',
  ];

}
