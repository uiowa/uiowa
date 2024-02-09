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
    'field_building_image' => 'imageUrl',
    'field_building_rr_multi_men' => 'multiUserRestroomsMen',
    'field_building_rr_multi_women' => 'multiUserRestroomsWomen',
    'field_building_rr_single_men' => 'singleUserRestroomsMen',
    'field_building_rr_single_women' => 'singleUserRestroomsWomen',
    'field_building_rr_single_neutral' => 'singleUserRestrooms',
    'field_building_lactation_rooms' => 'lactationRooms',
    'field_building_latitude' => 'latitude',
    'field_building_longitude' => 'longitude',
  ];

  /**
   * Process the field_building_hours.
   */
  public static function process($entity, $record): bool {
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $combined_hours = '';

    foreach ($days as $day) {
      $hours_property = $day . 'Hours';
      if (isset($record->{$hoursProperty})) {
        $formatted_hours = "<strong>" . ucfirst($day) . "</strong>: " . $record->{$hours_property};
        $combined_hours .= $formatted_hours . '<br />';
      }
    }

    // Assign the combined hours as processed text.
    $entity->set('field_building_hours', [
      'value' => $combined_hours,
      'format' => 'filtered_html',
    ]);

    $entity->save(TRUE);

    return TRUE;
  }

}
