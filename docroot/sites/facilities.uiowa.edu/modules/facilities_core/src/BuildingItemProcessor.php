<?php

namespace Drupal\facilities_core;

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
    'field_building_image:target_id' => 'imageUrl',
    'field_building_image:alt' => 'image_alt',
    'field_building_rr_multi_men' => 'multiUserRestroomsMen',
    'field_building_rr_multi_women' => 'multiUserRestroomsWomen',
    'field_building_rr_single_men' => 'singleUserRestroomsMen',
    'field_building_rr_single_women' => 'singleUserRestroomsWomen',
    'field_building_rr_single_neutral' => 'singleUserRestrooms',
    'field_building_lactation_rooms' => 'lactationRooms',
    'field_building_latitude' => 'latitude',
    'field_building_longitude' => 'longitude',
    'field_building_m_manager' => 'maintenanceManagerFullName',
    'field_building_ca_manager' => 'custodialAssistantManagerFullName',
    'field_main_coordinator_name' => 'mainFullName',
    'field_main_coordinator_title' => 'mainJobTitle',
    'field_main_coordinator_dept' => 'mainDepartment',
    'field_main_coordinator_email' => 'mainCampusEmail',
    'field_main_coordinator_phone' => 'mainCampusPhone',
    'field_alt1_coordinator_name' => 'alternateFullName1',
    'field_alt1_coordinator_title' => 'alternateJobTitle1',
    'field_alt1_coordinator_dept' => 'alternateDepartment1',
    'field_alt1_coordinator_email' => 'alternateCampusEmail1',
    'field_alt1_coordinator_phone' => 'alternateCampusPhone1',
    'field_alt2_coordinator_name' => 'alternateFullName2',
    'field_alt2_coordinator_title' => 'alternateJobTitle2',
    'field_alt2_coordinator_dept' => 'alternateDepartment2',
    'field_alt2_coordinator_email' => 'alternateCampusEmail2',
    'field_alt2_coordinator_phone' => 'alternateCampusPhone2',
    'field_alt3_coordinator_name' => 'alternateFullName3',
    'field_alt3_coordinator_title' => 'alternateJobTitle3',
    'field_alt3_coordinator_dept' => 'alternateDepartment3',
    'field_alt3_coordinator_email' => 'alternateCampusEmail3',
    'field_alt3_coordinator_phone' => 'alternateCampusPhone3',
    'field_alt4_coordinator_name' => 'alternateFullName4',
    'field_alt4_coordinator_title' => 'alternateJobTitle4',
    'field_alt4_coordinator_dept' => 'alternateDepartment4',
    'field_alt4_coordinator_email' => 'alternateCampusEmail4',
    'field_alt4_coordinator_phone' => 'alternateCampusPhone4',
  ];

  /**
   * Process the field_building_hours.
   */
  public static function process($entity, $record): bool {
    $updated = parent::process($entity, $record);

    $days = [
      'monday',
      'tuesday',
      'wednesday',
      'thursday',
      'friday',
      'saturday',
      'sunday',
    ];
    $combined_hours = '';

    foreach ($days as $day) {
      $hours_property = $day . 'Hours';
      if (isset($record->{$hours_property})) {
        $formatted_hours = '<strong>' . ucfirst($day) . '</strong>: ' . $record->{$hours_property};
        $combined_hours .= $formatted_hours . '<br />';
      }
    }
    if (!empty($combined_hours)) {
      // Assign the combined hours as processed text.
      $entity->set('field_building_hours', [
        'value' => $combined_hours,
        'format' => 'filtered_html',
      ]);
      $updated = TRUE;
    }

    return $updated;
  }

}
