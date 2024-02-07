<?php

namespace Drupal\facilities_core;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\uiowa_core\EntityItemProcessorBase;

/**
 * A processor for syncing building nodes.
 */
class BuildingItemProcessor extends EntityItemProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldMap = [
    'title' => 'buildingCommonName',
    'field_building_number' => 'buildingNumber',
    'field_building_abbreviation' => 'buildingAbbreviation',
    'field_building_address' => 'address',
    'field_building_area' => 'grossArea',
    'field_building_year_built' => 'yearBuilt',
    'field_building_ownership' => 'owned',
    'field_building_named_building' => 'namedBuilding',
    'field_building_image' => 'imageUrl',
    'field_building_rr_multi_men' => 'multiUserRestroomsMen',
    'field_building_rr_multi_women' => 'multiUserRestroomsWomen',
    'field_building_rr_single_men' => 'singleUserRestroomsMen',
    'field_building_rr_single_women' => 'singleUserRestroomsWomen',
    'field_building_rr_single_neutral' => 'singleUserRestrooms',
    'field_building_lactation_rooms' => 'lactationRooms',
    'field_building_latitude' => 'latitude',
    'field_building_longitude' => 'longitude',
    'field_building_coordinators' => 'buildingCoordinators',
  ];

  /**
   * Process the field_building_coordinators array and add as paragraphs.
   */
  public static function process($entity, $record): bool {
    $updated = FALSE;
    // parent::process($entity, $record);
    // Create paragraph.
    $main_coordinator = Paragraph::create([
      'type' => 'uiowa_building_coordinators',
      'field_b_coordinator_department' => $record->buildingCoordinators[0]->mainDepartment,
      'field_b_coordinator_email' => $record->buildingCoordinators[0]->mainCampusEmail,
      'field_b_coordinator_is_primary' => TRUE,
      'field_b_coordinator_job_title' => $record->buildingCoordinators[0]->mainJobTitle,
      'field_b_coordinator_name' => $record->buildingCoordinators[0]->mainFullName,
      'field_b_coordinator_phone_number' => $record->buildingCoordinators[0]->mainCampusPhone,
    ]);
    $main_coordinator->save();

    $entity->set('field_building_coordinators', [
      'target_id' => $main_coordinator->id(),
      'target_revision_id' => $main_coordinator->getRevisionId(),
    ]);
    $entity->save();
    $updated = TRUE;

    return $updated;
  }

}
