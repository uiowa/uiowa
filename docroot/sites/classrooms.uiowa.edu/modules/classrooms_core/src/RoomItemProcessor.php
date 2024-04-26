<?php

namespace Drupal\classrooms_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\uiowa_core\EntityItemProcessorBase;

/**
 * Process rooms information.
 */
class RoomItemProcessor extends EntityItemProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldMap = [
    'field_room_max_occupancy' => 'maxOccupancy',
    'field_room_name' => 'roomName',
    'field_room_type' => 'roomType',
    'field_room_responsible_unit' => 'acadOrgUnitName',
  ];

  /**
   * A container for a list of terms so they only need to be queried once.
   *
   * @var array[]
   *   The terms.
   */
  protected static array $terms = [
    'room_features' => [],
    'accessibility_features' => [],
    'technology_features' => [],
  ];

  /**
   * A map from term bundles to the field name they'll be associated with.
   *
   * @var array|string[]
   *   The map.
   */
  protected static array $termBundleToFieldMap = [
    'room_features' => 'field_room_features',
    'accessibility_features' => 'field_room_accessibility_feature',
    'technology_features' => 'field_room_technology_features',
  ];

  /**
   * {@inheritdoc}
   */
  public static function process($entity, $record): bool {
    // If we didn't have a record, a message should
    // already have been given, and we can skip processing.
    // Return false so we don't try to update.
    if (empty($record)) {
      return FALSE;
    }

    (new self)->processRecord($record);

    $updated = parent::process($entity, $record);

    // Mapping the Instructional Room Category field to the
    // roomCategory value from endpoint.
    if ($entity->hasField('field_room_instruction_category') && isset($record->roomCategory)) {
      $field_definition = $entity->getFieldDefinition('field_room_instruction_category')->getFieldStorageDefinition();
      $field_allowed_options = options_allowed_values($field_definition, $entity);
      if (array_key_exists($record->roomCategory, $field_allowed_options)) {
        if ($entity->get('field_room_instruction_category')->value !== $record->roomCategory) {
          $updated = TRUE;
          $entity->set('field_room_instruction_category', $record->roomCategory);
        }
      }
    }

    // Mapping Room Features and Technology Features fields
    // to the featureList value from endpoint.
    if (isset($record->featureList)) {
      foreach (array_keys(static::$termBundleToFieldMap) as $bundle) {
        $features[$bundle] = [];
        static::loadTerms($bundle);
        foreach (static::$terms[$bundle] as $term) {

          if ($api_mapping = $term->get('field_api_mapping')?->value) {
            if (in_array($api_mapping, $record->featureList)) {
              // If ($term->bundle() === 'room_features') {.
              $features[$bundle][] = $term->id();
              // }
            }
          }
        }

        if (!empty($features)) {
          // Cheat it a bit by fetching a string and exploding it
          // to end up with a basic array of target ids.
          $entity_features = $entity->get(static::$termBundleToFieldMap[$bundle])->getString();
          $entity_features = explode(', ', $entity_features);
          // Sort lists before comparing.
          sort($entity_features);
          sort($features[$bundle]);
          if ($entity_features !== $features[$bundle]) {
            $updated = TRUE;
            $entity->set(static::$termBundleToFieldMap[$bundle], $features[$bundle]);
          }
        }
      }
    }

    if (isset($record->regionList)) {
      $regions = [];
      static::loadTerms('scheduling_regions');
      foreach (static::$terms['scheduling_regions'] as $term) {
        if ($api_mapping = $term->get('field_api_mapping')?->value) {
          if (in_array($api_mapping, $record->regionList)) {
            // If we found a mappable region, set it.
            $regions[] = $term->id();
          }
        }
      }
      if (!empty($regions)) {
        $updated = TRUE;
        $entity->set('field_room_scheduling_regions', $regions);
      }
      $record->regionList = NULL;
    }

    return $updated;
  }

  /**
   * Returns a single record for the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to pull a record for.
   *
   * @return array|mixed
   *   The record or an empty array.
   */
  public static function getRecord(ContentEntityInterface $entity): mixed {
    // Get building ID.
    $building_id = $entity
      ->field_room_building_id
      ?->target_id;

    // Only fetch data if the $building_id is set. We may want to revisit this
    // if we add handling for this in MauiApi::getRoomData().
    if (!is_null($building_id)) {
      // Get room ID.
      $room_id = $entity
        ->field_room_room_id
        ?->value;

      // Fetch MAUI data.
      /** @var \Drupal\uiowa_maui\MauiApi $maui_api */
      $maui_api = \Drupal::service('uiowa_maui.api');
      $results = $maui_api->getRoomData($building_id, $room_id);
      // The record is returned inside the first entry
      // of the $data array. Return this if it exists, or an empty array.
      return $results[0] ?? [];
    }

    return [];
  }

  /**
   * Process the record before comparison.
   */
  public function processRecord(&$record) {
    // Set to null if we don't have a proper int.
    if (isset($record->maxOccupancy) && filter_var($record->maxOccupancy, FILTER_VALIDATE_INT) === FALSE) {
      $record->maxOccupancy = NULL;
    }
    // Account for the room name that is only a single space.
    if (isset($record->roomName) && strlen($record->roomName) <= 1) {
      $record->roomName = NULL;
    }
    if (isset($record->roomType)) {
      $record->roomType = $this->processRecordTerm($record->roomType, 'room_types', TRUE);
    }
    if (isset($record->acadOrgUnitName)) {
      $record->acadOrgUnitName = $this->processRecordTerm($record->acadOrgUnitName, 'units', TRUE);
    }
  }

  /**
   * Process a record's value that maps to a taxonomy term.
   *
   * @param string $term_name
   *   The specific term name to match.
   * @param string $vid
   *   The taxonomy vid with which to search.
   * @param bool $create_new
   *   Whether a new term should be created if no match was found.
   *
   * @return string
   *   The matching term id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected static function processRecordTerm(string $term_name, string $vid, bool $create_new = TRUE) {
    // Returns all terms matching name within vocabulary.
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'name' => $term_name,
        'vid' => $vid,
      ]);
    if (empty($term)) {
      // If term does not exist create it.
      if ($create_new === TRUE) {
        $new_term = Term::create([
          'vid' => $vid,
          'name' => $term_name,
        ]);
        $new_term->save();
        return $new_term->id();
      }
      else {
        return '';
      }
    }
    // In this particular instance, we're only
    // keeping the first term.
    return array_key_first($term);
  }

  /**
   * Helper function to load terms.
   */
  protected static function loadTerms(string $bundle): void {
    if (empty(static::$terms[$bundle])) {
      $tids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', $bundle)
        ->accessCheck()
        ->execute();

      if ($tids) {
        $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
        static::$terms[$bundle] = $storage->loadMultiple($tids);
      }
    }
  }

}
