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
    'field_project_building' => 'projectBuilding',
    'field_project_building_alt' => 'buildingName',
    'field_project_scope' => 'projectScope',
    'field_project_status' => 'projectStatus',
    'field_project_square_footage' => 'grossSqFeet',
    'field_project_pre_bid_location' => 'preBidLocation',
    'field_project_awarded_to' => 'vendorName',
    'field_project_architect' => 'primaryConsultant',
    'field_project_bid_date' => 'bidOpeningDate',
    'field_project_constr_start_date' => 'constructionStartDate',
    'field_project_pre_bid_date' => 'preBidDate',
    'field_project_sub_complete_date' => 'substantialCompletionDate',
    'field_project_estimated_cost' => 'estimatedAmount',
    'field_project_is_featured' => 'isFeatured',
    'field_project_is_capital' => 'isCapital',
  ];

}
