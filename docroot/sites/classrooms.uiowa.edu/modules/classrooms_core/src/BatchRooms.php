<?php
namespace Drupal\classrooms_core;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Class BatchRooms.
 */
class BatchRooms {

  /**
   * Batch process callback.
   *
   * @param int $batch_id
   *   Id of the batch.
   * @param $nodes
   *   Individual nodes to be processed.
   * @param object $context
   *   Context for operations.
   */
  public static function processNode($batch_id, $nodes, &$context) {
    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@id"', [
      '@id' => $batch_id,
    ]);

    foreach ($nodes as $node) {

      if (!$node instanceof FieldableEntityInterface) {
        continue;
      }

      if ($node->getRevisionId() == 9154) {
        $foo = 'bar';
      }

      if ($node->hasField('field_room_building_id') &&
        !$node->get('field_room_building_id')->isEmpty() &&
        $node->hasField('field_room_room_id') &&
        !$node->get('field_room_room_id')->isEmpty()
      ) {
        $building_id = $node->get('field_room_building_id')?->getString();
        $room_id = $node->get('field_room_room_id')->value;
      }
      else {
        continue;
      }

      $updated = FALSE;

      // Grab MAUI room data.
      $data = \Drupal::service('uiowa_maui.api')
        ->getRoomData($building_id, $room_id);

      // If we weren't able to get data for this room,
      // log a notice and move on to the next one.
      if (!$data) {
        $context['message'] = t('No data found for @building @room', [
          '@building' => $building_id,
          '@room' => $room_id,
        ]);
        continue;
      }

      // If existing, update values if different.
      // Comparing the Max Occupancy field
      // to the maxOccupancy value from endpoint.
      if ($node->hasField('field_room_max_occupancy') && isset($data[0]->maxOccupancy)) {
        if (filter_var($data[0]->maxOccupancy, FILTER_VALIDATE_INT) !== FALSE) {
          if ($node->get('field_room_max_occupancy')->value !== $data[0]->maxOccupancy) {
            $updated = TRUE;
            \Drupal::database()
              ->update('node__field_room_max_occupancy')
              ->fields([
                'field_room_max_occupancy_value' => $data[0]->maxOccupancy,
              ])
              ->condition('revision_id', $node->getRevisionId(), '=')
              ->execute();
          }
        }
      }

      // Comparing the Room Name field to the roomName value from endpoint.
      if ($node->hasField('field_room_name') && isset($data[0]->roomName)) {
        if (strlen($data[0]->roomName) > 1) {
          if ($node->get('field_room_name')->value !== $data[0]->roomName) {
            $updated = TRUE;
            \Drupal::database()
              ->update('node__field_room_name')
              ->fields([
                'field_room_name_value' => $data[0]->roomName,
              ])
              ->condition('revision_id', $node->getRevisionId(), '=')
              ->execute();
          }
        }
      }

      // Comparing the Instructional Room Category field to the
      // roomCategory value from endpoint.
      if ($node->hasField('field_room_instruction_category') && isset($data[0]->roomCategory)) {
        $field_definition = $node->getFieldDefinition('field_room_instruction_category')->getFieldStorageDefinition();
        $field_allowed_options = options_allowed_values($field_definition, $node);
        if (array_key_exists($data[0]->roomCategory, $field_allowed_options)) {
          if ($node->get('field_room_instruction_category')->value !== $data[0]->roomCategory) {
            $updated = TRUE;
            \Drupal::database()
              ->update('node__field_room_instruction_category')
              ->fields([
                'field_room_instruction_category_value' => $data[0]->roomCategory,
              ])
              ->condition('revision_id', $node->getRevisionId(), '=')
              ->execute();
          }
        }
      }

      // Comparing the Room Type field to the roomType value from endpoint.
      if ($node->hasField('field_room_type') && isset($data[0]->roomType)) {
        // Returns all terms matching name within vocabulary.
        $term = \Drupal::service('entity_type.manager')
          ->getStorage('taxonomy_term')
          ->loadByProperties([
            'name' => $data[0]->roomType,
            'vid' => 'room_types',
          ]);
        if (empty($term) || (int) $node->get('field_room_type')->getString() !== array_key_first($term)) {
          $updated = TRUE;
          \Drupal::database()
            ->update('node__field_room_type')
            ->fields([
              'field_room_type_target_id' => array_key_first($term),
            ])
            ->condition('revision_id', $node->getRevisionId(), '=')
            ->execute();
        }
      }

      // Comparing the Responsible Unit field to the
      // acadOrgUnitName value from endpoint.
      if ($node->hasField('field_room_responsible_unit') && isset($data[0]->acadOrgUnitName)) {
        // Returns all terms matching name within vocabulary.
        $term = \Drupal::service('entity_type.manager')
          ->getStorage('taxonomy_term')
          ->loadByProperties([
            'name' => $data[0]->acadOrgUnitName,
            'vid' => 'units',
          ]);
        if (empty($term) || (int) $node->get('field_room_responsible_unit')->getString() !== array_key_first($term)) {
          $updated = TRUE;
          \Drupal::database()
            ->update('node__field_room_responsible_unit')
            ->fields([
              'field_room_responsible_unit_target_id' => array_key_first($term),
            ])
            ->condition('revision_id', $node->getRevisionId(), '=')
            ->execute();
        }
      }

      // Comparing Room Features and Technology Features fields
      // to the featureList value from endpoint.
      if (isset($data[0]->featureList)) {
        $query = \Drupal::service('entity_type.manager')
          ->getStorage('taxonomy_term')
          ->getQuery()
          ->orConditionGroup()
          ->condition('vid', 'room_features')
          ->condition('vid', 'technology_features');

        $tids = \Drupal::service('entity_type.manager')
          ->getStorage('taxonomy_term')
          ->getQuery()
          ->condition($query)
          ->execute();
        if ($tids) {
          $terms = \Drupal::service('entity_type.manager')
            ->getStorage('taxonomy_term')
            ->loadMultiple($tids);
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
            $node_features = $node->get('field_room_features')->getString();
            $node_features = explode(', ', $node_features);
            if ($node_features !== $room_features) {
              $updated = TRUE;
              // If the data from maui has fewer room features
              // than are currently on the node, remove the excess.
              if (count($room_features) < count($node_features)) {
                foreach (range(count($room_features), count($node_features)) as $delta) {
                  \Drupal::database()
                    ->delete('node__field_room_features')
                    ->condition('revision_id', $node->getRevisionId(), '=')
                    ->condition('delta', $delta, '=')
                    ->execute();
                }
              }
              foreach ($room_features as $delta => $target_id) {
                // As long as the old room features was equal to or
                // more than the number of new features, we can
                // re-use the table entries. Any additional room features
                // will need to be inserted.
                if ($delta < count($node_features) - 1) {
                  \Drupal::database()
                    ->update('node__field_room_features')
                    ->fields([
                      'field_room_features_target_id' => $target_id,
                    ])
                    ->condition('revision_id', $node->getRevisionId(), '=')
                    ->condition('delta', $delta, '=')
                    ->execute();
                }
                else {
                  \Drupal::database()
                    ->insert('node__field_room_features')
                    ->fields([
                      'bundle' => 'room',
                      'deleted' => 0,
                      'entity_id' => $node->id(),
                      'revision_id' => $node->getRevisionId(),
                      'langcode' => 'en',
                      'delta' => $delta,
                      'field_room_features_target_id' => $target_id,
                    ])
                    ->execute();
                }
              }
            }
          }
          if (!empty($tech_features)) {
            $node_tech_features = $node->get('field_room_technology_features')->getString();
            $node_tech_features = explode(', ', $node_tech_features);
            if ($node_tech_features !== $tech_features) {
              $updated = TRUE;
              // If the data from maui has fewer room features
              // than are currently on the node, remove the excess.
              if (count($tech_features) < count($node_tech_features)) {
                foreach (range(count($tech_features), count($node_tech_features)) as $delta) {
                  \Drupal::database()
                    ->delete('node__field_room_technology_features')
                    ->condition('revision_id', $node->getRevisionId(), '=')
                    ->condition('delta', $delta, '=')
                    ->execute();
                }
              }
              foreach ($tech_features as $delta => $target_id) {
                // As long as the old room features was equal to or
                // more than the number of new features, we can
                // re-use the table entries. Any additional room features
                // will need to be inserted.
                if ($delta < count($room_features) - 1) {
                  \Drupal::database()
                    ->update('node__field_room_technology_features')
                    ->fields([
                      'field_room_technology_features_target_id' => $target_id,
                    ])
                    ->condition('revision_id', $node->getRevisionId(), '=')
                    ->condition('delta', $delta, '=')
                    ->execute();
                }
                else {
                  \Drupal::database()
                    ->insert('node__field_room_technology_features')
                    ->fields([
                      'bundle' => 'room',
                      'deleted' => 0,
                      'entity_id' => $node->id(),
                      'revision_id' => $node->getRevisionId(),
                      'langcode' => 'en',
                      'delta' => $delta,
                      'field_room_technology_features_target_id' => $target_id,
                    ])
                    ->execute();
                }
              }
            }
          }
        }
      }

      // Comparing the Scheduling Regions field to the
      // regionList value from endpoint.
      if (isset($data[0]->regionList)) {
        $query = \Drupal::service('entity_type.manager')
          ->getStorage('taxonomy_term')
          ->getQuery()
          ->condition('vid', 'scheduling_regions')
          ->execute();

        if ($query) {
          $terms = \Drupal::service('entity_type.manager')
            ->getStorage('taxonomy_term')
            ->loadMultiple($query);
          foreach ($terms as $term) {
            if ($api_mapping = $term->get('field_api_mapping')?->value) {
              if (in_array($api_mapping, $data[0]->regionList)) {
                if ($node->get('field_room_scheduling_regions')->getString() !== $term->id()) {
                  $updated = TRUE;
                  \Drupal::database()
                    ->update('node__field_room_scheduling_regions')
                    ->fields([
                      'field_room_scheduling_regions_target_id' => $term->id(),
                    ])
                    ->condition('revision_id', $node->getRevisionId(), '=')
                    ->execute();
                  $updated = TRUE;
                }
              }
            }
          }
        }
      }

      if ($updated === TRUE) {
        // Optional message displayed under the progressbar.
        $context['results'][] = $node->id();
      }
    }
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public static function processNodeFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $messenger->addMessage(t('@count results updated. That is neat.', [
        '@count' => count($results)
      ]));
    }
  }
}
