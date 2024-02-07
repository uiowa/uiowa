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
    $coordinator_array = [];

    if ($record->buildingCoordinators[0]->mainFullName != NULL) {
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
      $main_array = [
        'target_id' => $main_coordinator->id(),
        'target_revision_id' => $main_coordinator->getRevisionId(),
      ];
      $coordinator_array[] = $main_array;
    }

    if ($record->buildingCoordinators[0]->alternateFullName1 != NULL) {
      $alternate_coordinator_1 = Paragraph::create([
        'type' => 'uiowa_building_coordinators',
        'field_b_coordinator_department' => $record->buildingCoordinators[0]->alternateDepartment1,
        'field_b_coordinator_email' => $record->buildingCoordinators[0]->alternateCampusEmail1,
        'field_b_coordinator_is_primary' => FALSE,
        'field_b_coordinator_job_title' => $record->buildingCoordinators[0]->alternateJobTitle1,
        'field_b_coordinator_name' => $record->buildingCoordinators[0]->alternateFullName1,
        'field_b_coordinator_phone_number' => $record->buildingCoordinators[0]->alternateCampusPhone1,
      ]);
      $alternate_coordinator_1->save();
      $alt_1_array = [
        'target_id' => $alternate_coordinator_1->id(),
        'target_revision_id' => $alternate_coordinator_1->getRevisionId(),
      ];
      $coordinator_array[] = $alt_1_array;
    }

    if ($record->buildingCoordinators[0]->alternateFullName2 != NULL) {
      $alternate_coordinator_2 = Paragraph::create([
        'type' => 'uiowa_building_coordinators',
        'field_b_coordinator_department' => $record->buildingCoordinators[0]->alternateDepartment2,
        'field_b_coordinator_email' => $record->buildingCoordinators[0]->alternateCampusEmail2,
        'field_b_coordinator_is_primary' => FALSE,
        'field_b_coordinator_job_title' => $record->buildingCoordinators[0]->alternateJobTitle2,
        'field_b_coordinator_name' => $record->buildingCoordinators[0]->alternateFullName2,
        'field_b_coordinator_phone_number' => $record->buildingCoordinators[0]->alternateCampusPhone2,
      ]);
      $alternate_coordinator_2->save();
      $alt_2_array = [
        'target_id' => $alternate_coordinator_2->id(),
        'target_revision_id' => $alternate_coordinator_2->getRevisionId(),
      ];
      $coordinator_array[] = $alt_2_array;
    }

    if ($record->buildingCoordinators[0]->alternateFullName3 != NULL) {
      $alternate_coordinator_3 = Paragraph::create([
        'type' => 'uiowa_building_coordinators',
        'field_b_coordinator_department' => $record->buildingCoordinators[0]->alternateDepartment3,
        'field_b_coordinator_email' => $record->buildingCoordinators[0]->alternateCampusEmail3,
        'field_b_coordinator_is_primary' => FALSE,
        'field_b_coordinator_job_title' => $record->buildingCoordinators[0]->alternateJobTitle3,
        'field_b_coordinator_name' => $record->buildingCoordinators[0]->alternateFullName3,
        'field_b_coordinator_phone_number' => $record->buildingCoordinators[0]->alternateCampusPhone3,
      ]);
      $alternate_coordinator_3->save();
      $alt_3_array = [
        'target_id' => $alternate_coordinator_3->id(),
        'target_revision_id' => $alternate_coordinator_3->getRevisionId(),
      ];
      $coordinator_array[] = $alt_3_array;
    }

    if ($record->buildingCoordinators[0]->alternateFullName4 != NULL) {
      $alternate_coordinator_4 = Paragraph::create([
        'type' => 'uiowa_building_coordinators',
        'field_b_coordinator_department' => $record->buildingCoordinators[0]->alternateDepartment4,
        'field_b_coordinator_email' => $record->buildingCoordinators[0]->alternateCampusEmail4,
        'field_b_coordinator_is_primary' => FALSE,
        'field_b_coordinator_job_title' => $record->buildingCoordinators[0]->alternateJobTitle4,
        'field_b_coordinator_name' => $record->buildingCoordinators[0]->alternateFullName4,
        'field_b_coordinator_phone_number' => $record->buildingCoordinators[0]->alternateCampusPhone4,
      ]);
      $alternate_coordinator_4->save();
      $alt_4_array = [
        'target_id' => $alternate_coordinator_4->id(),
        'target_revision_id' => $alternate_coordinator_4->getRevisionId(),
      ];
      $coordinator_array[] = $alt_4_array;
    }

    $entity->set('field_building_coordinators', $coordinator_array);
    $entity->save();
    $updated = TRUE;

    return $updated;
  }

}
