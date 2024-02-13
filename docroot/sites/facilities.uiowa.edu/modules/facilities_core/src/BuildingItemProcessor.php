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
    'field_building_ca_manager' => 'custodialAssistantManagerFullName',
    'field_building_m_manager' => 'maintenanceManagerFullName',
    'field_building_date_updated' => 'updatedDate',
  ];

  /**
   * Process the field_building_coordinators array.
   */
  public static function process($entity, $record): bool {

    if (isset($record->buildingCoordinators[0]->updatedDate)) {
      $coordinator_array = [];

      // Comparing string to int as date int too large to store in db.
      if ($entity->get('field_building_date_updated')->value != ($record->buildingCoordinators[0]->updatedDate)) {
        $entity->set('field_building_date_updated', $record->buildingCoordinators[0]->updatedDate);

        // Mapping manager fields from coordinators array.
        if (isset($record->buildingCoordinators[0]->custodialAssistantManagerFullName)) {
          if ($entity->get('field_building_ca_manager')->value !== $record->buildingCoordinators[0]->custodialAssistantManagerFullName) {
            $entity->set('field_building_ca_manager', $record->buildingCoordinators[0]->custodialAssistantManagerFullName);
          }
        }
        if (isset($record->buildingCoordinators[0]->maintenanceManagerFullName)) {
          if ($entity->get('field_building_m_manager')->value !== $record->buildingCoordinators[0]->maintenanceManagerFullName) {
            $entity->set('field_building_m_manager', $record->buildingCoordinators[0]->maintenanceManagerFullName);
          }
        }

        // Check if primary coordinator exists.
        if (isset($record->buildingCoordinators[0]->mainFullName)) {
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

        // Check if alternate coordinators exist.
        $alternates = ['1', '2', '3', '4'];
        foreach ($alternates as $alt_number) {
          if (isset($record->buildingCoordinators[0]->{'alternateFullName' . $alt_number})) {
            $alternate_coordinator = Paragraph::create([
              'type' => 'uiowa_building_coordinators',
              'field_b_coordinator_department' => $record->buildingCoordinators[0]->{'alternateDepartment' . $alt_number},
              'field_b_coordinator_email' => $record->buildingCoordinators[0]->{'alternateCampusEmail' . $alt_number},
              'field_b_coordinator_is_primary' => FALSE,
              'field_b_coordinator_job_title' => $record->buildingCoordinators[0]->{'alternateJobTitle' . $alt_number},
              'field_b_coordinator_name' => $record->buildingCoordinators[0]->{'alternateFullName' . $alt_number},
              'field_b_coordinator_phone_number' => $record->buildingCoordinators[0]->{'alternateCampusPhone' . $alt_number},
            ]);
            $alternate_coordinator->save();
            $alt_array = [
              'target_id' => $alternate_coordinator->id(),
              'target_revision_id' => $alternate_coordinator->getRevisionId(),
            ];
            $coordinator_array[] = $alt_array;
          }
        }

        // Update building entity with new coordinator paragraph(s).
        $entity->set('field_building_coordinators', $coordinator_array);
        $entity->save();

        return TRUE;
      }
    }
    return FALSE;
  }

}
