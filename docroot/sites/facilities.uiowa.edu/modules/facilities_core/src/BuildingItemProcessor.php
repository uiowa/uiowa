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
    'field_building_energy_dashboard' => 'energyLink',
    'field_building_named_building' => 'namedBuilding',
    'field_building_image:target_id' => 'imageUrl',
    'field_building_image:alt' => 'image_alt',
    'field_building_rr_multi_men' => 'multiUserRestroomsMen',
    'field_building_rr_multi_women' => 'multiUserRestroomsWomen',
    'field_building_rr_single_men' => 'singleUserRestroomsMen',
    'field_building_rr_single_women' => 'singleUserRestroomsWomen',
    'field_building_rr_single_neutral' => 'singleUserRestrooms',
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

    // Map API arrays to paragraph reference fields.
    $service_resource_fields = [
      'field_building_lactation_rooms' => 'lactationRooms',
      'field_building_aed' => 'aed',
      'field_building_stop_the_bleed' => 'stopTheBleed',
      'field_building_evac_chairs' => 'evacChairs',
    ];

    foreach ($service_resource_fields as $field_name => $api_key) {
      if (static::processServiceResourceField($entity, $field_name, $record->{$api_key} ?? NULL)) {
        $updated = TRUE;
      }
    }

    return $updated;
  }

  /**
   * Maps resource property names to service_resource paragraph field names.
   */
  protected const RESOURCE_MAP = [
    'floor' => 'field_sr_floor',
    'room' => 'field_sr_room',
    'contactName' => 'field_sr_contact_name',
    'contactEmail' => 'field_sr_contact_email',
    'contactPhone' => 'field_sr_contact_phone',
    'contactCampusAddress' => 'field_sr_contact_address',
    'facilityEquipment' => 'field_sr_equipment',
    'additionalLocationGuide' => 'field_sr_location_guide',
    'accessInformation' => 'field_sr_access_info',
  ];

  /**
   * Sync a service_resource paragraph field from an API array.
   *
   * Deletes existing paragraphs on the field and recreates from API data.
   * Returns TRUE if the field value changed.
   *
   * @param mixed $entity
   *   The building node entity.
   * @param string $field_name
   *   The entity reference revisions field name.
   * @param mixed $items
   *   Array of stdClass objects from the API, or NULL.
   *
   * @return bool
   *   TRUE if the field was updated.
   */
  protected static function processServiceResourceField($entity, string $field_name, mixed $items): bool {
    $existing = $entity->get($field_name)->referencedEntities();
    $incoming = is_array($items) ? $items : [];

    // No existing, no incoming — nothing to do.
    if (empty($existing) && empty($incoming)) {
      return FALSE;
    }

    // Delete existing paragraphs.
    foreach ($existing as $paragraph) {
      $paragraph->delete();
    }

    if (empty($incoming)) {
      $entity->set($field_name, []);
      return TRUE;
    }

    $new = [];
    foreach ($incoming as $item) {
      if (!is_object($item)) {
        continue;
      }
      $paragraph = Paragraph::create(['type' => 'service_resource']);
      foreach (static::RESOURCE_MAP as $resource_property => $sr_field) {
        $value = $item->{$resource_property} ?? NULL;
        if (!is_null($value)) {
          $paragraph->set($sr_field, $value);
        }
      }
      $paragraph->save();
      $new[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }

    $entity->set($field_name, $new);
    return TRUE;
  }

}
