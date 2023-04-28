<?php

namespace Drupal\classrooms_core;

use Drupal\taxonomy\Entity\Term;

/**
 * Process rooms information.
 */
class RoomProcessor {

  /**
   * Import individual room information from maui.
   */
  public static function process($entity): bool {
    $updated = FALSE;

    $building_id = $entity
      ->field_room_building_id
      ?->target_id;
    $room_id = $entity
      ->field_room_room_id
      ?->value;

    // Grab MAUI room data.
    $maui_api = \Drupal::service('uiowa_maui.api');
    $data = $maui_api->getRoomData($building_id, $room_id);
    if ($data) {
      // Mapping the Max Occupancy field
      // to the maxOccupancy value from endpoint.
      if ($entity->hasField('field_room_max_occupancy') && isset($data[0]->maxOccupancy)) {
        if (filter_var($data[0]->maxOccupancy, FILTER_VALIDATE_INT) !== FALSE) {
          if ($entity->get('field_room_max_occupancy')->value !== $data[0]->maxOccupancy) {
            $updated = TRUE;
            $entity->set('field_room_max_occupancy', $data[0]->maxOccupancy);
          }
        }

        // Mapping the Room Name field to the roomName value from endpoint.
        if ($entity->hasField('field_room_name') && isset($data[0]->roomName)) {
          if (strlen($data[0]->roomName) > 1) {
            if ($entity->get('field_room_name')->value !== $data[0]->roomName) {
              $updated = TRUE;
              $entity->set('field_room_name', $data[0]->roomName);
            }
          }
        }

        // Mapping the Instructional Room Category field to the
        // roomCategory value from endpoint.
        if ($entity->hasField('field_room_instruction_category') && isset($data[0]->roomCategory)) {
          $field_definition = $entity->getFieldDefinition('field_room_instruction_category')->getFieldStorageDefinition();
          $field_allowed_options = options_allowed_values($field_definition, $entity);
          if (array_key_exists($data[0]->roomCategory, $field_allowed_options)) {
            if ($entity->get('field_room_instruction_category')->value !== $data[0]->roomCategory) {
              $updated = TRUE;
              $entity->set('field_room_instruction_category', $data[0]->roomCategory);
            }
          }
        }

        // Mapping the Room Type field to the roomType value from endpoint.
        if ($entity->hasField('field_room_type') && isset($data[0]->roomType)) {
          // Returns all terms matching name within vocabulary.
          $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties([
              'name' => $data[0]->roomType,
              'vid' => 'room_types',
            ]);
          if (empty($term)) {
            // If term does not exist create it.
            $new_term = Term::create([
              'vid' => 'room_types',
              'name' => $data[0]->roomType,
            ]);
            $new_term->save();
            $updated = TRUE;
            $entity->set('field_room_type', [$new_term->id()]);
          }
          elseif ((int) $entity->get('field_room_type')->getString() !== array_key_first($term)) {
            // Set based on first (and hopefully only) result.
            $updated = TRUE;
            $entity->set('field_room_type', [array_key_first($term)]);
          }
        }

        // Mapping the Responsible Unit field to the
        // acadOrgUnitName value from endpoint.
        if ($entity->hasField('field_room_responsible_unit') && isset($data[0]->acadOrgUnitName)) {
          // Returns all terms matching name within vocabulary.
          $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties([
              'name' => $data[0]->acadOrgUnitName,
              'vid' => 'units',
            ]);
          if (empty($term)) {
            // If term does not exist create it.
            $new_term = Term::create([
              'vid' => 'units',
              'name' => $data[0]->acadOrgUnitName,
            ]);
            $new_term->save();
            $updated = TRUE;
            $entity->set('field_room_responsible_unit', [$new_term->id()]);
          }
          elseif ((int) $entity->get('field_room_responsible_unit')->getString() !== array_key_first($term)) {
            $updated = TRUE;
            // Set based on first (and hopefully only) result.
            $entity->set('field_room_responsible_unit', [array_key_first($term)]);
          }
        }

        // Mapping Room Features and Technology Features fields
        // to the featureList value from endpoint.
        if (isset($data[0]->featureList)) {
          $query = \Drupal::entityQuery('taxonomy_term')->orConditionGroup()
            ->condition('vid', 'room_features')
            ->condition('vid', 'technology_features');

          $tids = \Drupal::entityQuery('taxonomy_term')
            ->condition($query)
            ->execute();
          if ($tids) {
            $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
            $terms = $storage->loadMultiple($tids);
            $room_features = [];
            $tech_features = [];
            foreach ($terms as $term) {
              if ($api_mapping = $term->get('field_api_mapping')?->value) {
                if (in_array($api_mapping, $data[0]->featureList)) {
                  if ($term->bundle() === 'room_features') {
                    $room_features[] = $term->id();
                  }
                  else {
                    $tech_features[] = $term->id();
                  }
                }
              }
            }
            if (!empty($room_features)) {
              // Cheat it a bit by fetching a string and exploding it
              // to end up with a basic array of target ids.
              $entity_features = $entity->get('field_room_features')->getString();
              $entity_features = explode(', ', $entity_features);
              // Sort lists before comparing.
              sort($entity_features);
              sort($room_features);
              if ($entity_features !== $room_features) {
                $updated = TRUE;
                $entity->set('field_room_features', $room_features);
              }
            }

            if (!empty($tech_features)) {
              // Cheat it a bit by fetching a string and exploding it
              // to end up with a basic array of target ids.
              $entity_features = $entity->get('field_room_technology_features')->getString();
              $entity_features = explode(', ', $entity_features);
              // Sort lists before comparing.
              sort($entity_features);
              sort($tech_features);
              if ($entity_features !== $tech_features) {
                $updated = TRUE;
                $entity->set('field_room_technology_features', $tech_features);
              }
            }
          }
        }

        // Mapping the Scheduling Regions field to the
        // regionList value from endpoint.
        if (isset($data[0]->regionList)) {
          $query = \Drupal::entityQuery('taxonomy_term')
            ->condition('vid', 'scheduling_regions')
            ->execute();

          if ($query) {
            $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
            $terms = $storage->loadMultiple($query);
            foreach ($terms as $term) {
              if ($api_mapping = $term->get('field_api_mapping')?->value) {
                if (in_array($api_mapping, $data[0]->regionList)) {
                  if ($entity->get('field_room_scheduling_regions')->getString() !== $term->id()) {
                    $updated = TRUE;
                    $entity->set('field_room_scheduling_regions', $term->id());
                    break;
                  }
                }
              }
            }
          }
        }
      }
    }
    return $updated;
  }

}
