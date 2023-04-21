<?php

namespace Drupal\classrooms_core\Commands;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\classrooms_core\Entity\Building;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class ClassroomsCoreCommands extends DrushCommands {
  use LoggerChannelTrait;

  /**
   * The account_switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   */
  public function __construct(AccountSwitcherInterface $accountSwitcher) {
    $this->accountSwitcher = $accountSwitcher;
  }

  /**
   * Triggers the building import.
   *
   * @command classrooms_core:buildings
   * @aliases classrooms-buildings
   * @usage classrooms_core:buildings
   *  Ideally this is done as a crontab that is only run once a day.
   */
  public function getBuildings() {
    $entities_created = 0;
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));

    $cid = 'uiowa_maui:request:buildings_filtered';
    if ($cached = \Drupal::cache('uiowa_maui')->get($cid)) {
      $buildings = $cached->data;
    }
    else {
      // Request from MAUI API and then filter based on Classroom's requirements.
      $maui_api = \Drupal::service('uiowa_maui.api');
      $data = $maui_api->getClassroomsData();
      $buildings = [];
      $filters = [
        '1) University Classrooms - Level 1',
        '1) University Classrooms - Original',
        '1) University Classrooms',
        '1) University Classrooms - Study Space',
        '1) Programmed Classrooms - Level 2',
        'Classroom-Programmed',
      ];

      foreach ($data as $room) {
        $category = array_intersect($filters, $room->regionList);

        if ($category) {
          // Get the building id and name in the format we store them in.
          $buildings[strtolower($room->buildingCode)] = ucwords(strtolower($room->buildingName));
        }
      }
      // Create a cache item set to 6 hours.
      $request_time = \Drupal::time()->getRequestTime();
      \Drupal::cache('uiowa_maui')->set($cid, $buildings, $request_time + 21600);
    }

    if (!empty($buildings)) {
      // Check for existing building config entities before creating.
      $query = \Drupal::entityQuery('building')
        ->accessCheck(TRUE);
      $entities = $query->execute();

      foreach ($buildings as $building_id => $building_name) {
        if (!in_array($building_id, $entities)) {
          $building = Building::create([
            'id' => $building_id,
            'label' => $building_name,
            'status' => 1,
          ]);
          $building->save();
          $entities_created++;
        }
      }
    }

    if ($entities_created > 0) {
      $arguments = [
        '@count' => $entities_created,
      ];
      $this->getLogger('classrooms_core')->notice('@count buildings were created. That is neat.', $arguments);
    }
    else {
      $this->getLogger('classrooms_core')->notice('Bummer. No new buildings were created. Maybe next time.');
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

  /**
   * Triggers the classrooms rooms import.
   *
   * @command classrooms_core:rooms_import
   * @aliases classrooms-rooms
   * @usage classrooms_core:rooms_import
   *  Ideally this is done as a crontab that is only run once a day.
   */
  public function importRooms() {
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));

    // Establish a count for message at the end.
    $entities_updated = 0;

    // Get existing room nodes.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'room')
      ->accessCheck(TRUE);
    $entities = $query->execute();

    // If we don't have any entities, send a message
    // and we're done.
    if (empty($entities)) {
      $this->getLogger('classrooms_core')->notice('No rooms available to update.');

      // Switch user back.
      $this->accountSwitcher->switchBack();
      return;
    }

    // Retrieve building and room number values from existing nodes.
    $storage = \Drupal::getContainer()->get('entity_type.manager')->getStorage('node');
    $nodes = $storage->loadMultiple($entities);
    $existing_nodes = [];
    foreach ($nodes as $nid => $node) {
      if ($node instanceof FieldableEntityInterface) {
        if ($node->hasField('field_room_building_id') &&
          !$node->get('field_building_number')->isEmpty() &&
          $node->hasField('field_room_room_id') &&
          !$node->get('field_room_room_id')->isEmpty()
        ) {
          $existing_nodes[$nid] = [
            'building_id' => $node->get('field_room_building_id')->value,
            'room_id' => $node->get('field_room_room_id')->value,
          ];
        }
      }
    }

    foreach ($existing_nodes as $nid => $info) {
      // Grab MAUI room data.
      $maui_api = \Drupal::service('uiowa_maui.api');
      $data = $maui_api->getRoomData($info['building_id'], $info['room_id']);

      // If we weren't able to get data for this room,
      // log a notice and move on to the next one.
      if (!$data) {
        $this->getLogger('classrooms_core')->notice('No data found for @building @room', [
          '@building' => $info['building_id'],
          '@room' => $info['room_id'],
        ]);
        continue;
      }

      // Mapping the Max Occupancy field to the maxOccupancy value from endpoint.
      if ($entity->hasField('field_room_max_occupancy') && isset($data[0]->maxOccupancy)) {
        if (filter_var($data[0]->maxOccupancy, FILTER_VALIDATE_INT) !== FALSE) {
          $entity->set('field_room_max_occupancy', $data[0]->maxOccupancy);
        }
      }

      // Mapping the Room Name field to the roomName value from endpoint.
      if ($entity->hasField('field_room_name') && isset($data[0]->roomName)) {
        if (strlen($data[0]->roomName) > 1) {
          $entity->set('field_room_name', $data[0]->roomName);
        }
      }

      // Mapping the Instructional Room Category field to the
      // roomCategory value from endpoint.
      if ($entity->hasField('field_room_instruction_category') && isset($data[0]->roomCategory)) {
        $field_definition = $entity->getFieldDefinition('field_room_instruction_category')->getFieldStorageDefinition();
        $field_allowed_options = options_allowed_values($field_definition, $entity);
        if (array_key_exists($data[0]->roomCategory, $field_allowed_options)) {
          $entity->set('field_room_instruction_category', $data[0]->roomCategory);
        }
      }

      // Mapping the Room Type field to the roomType value from endpoint.
      if ($entity->hasField('field_room_type') && isset($data[0]->roomType)) {
        // Returns all terms matching name within vocabulary.
        $term = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->loadByProperties(['name' => $data[0]->roomType, 'vid' => 'room_types']);
        if (!empty($term)) {
          // Set based on first (and hopefully only) result.
          $entity->set('field_room_type', [array_key_first($term)]);
        }
        else {
          // If term does not exist create it.
          $new_term = Term::create([
            'vid' => 'room_types',
            'name' => $data[0]->roomType,
          ]);
          $new_term->save();
          $entity->set('field_room_type', [$new_term->id()]);
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
        if (!empty($term)) {
          // Set based on first (and hopefully only) result.
          $entity->set('field_room_responsible_unit', [array_key_first($term)]);
        }
        else {
          // If term does not exist create it.
          $new_term = Term::create([
            'vid' => 'units',
            'name' => $data[0]->acadOrgUnitName,
          ]);
          $new_term->save();
          $entity->set('field_room_responsible_unit', [$new_term->id()]);
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
            $entity->set('field_room_features', $room_features);
          }
          if (!empty($tech_features)) {
            $entity->set('field_room_technology_features', $tech_features);
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
                $entity->set('field_room_scheduling_regions', $term->id());
                break;
              }
            }
          }
        }
      }

    }

    // @todo Update this with classrooms-specific logic.
    if ($data) {
      $buildings = [];
      foreach ($data as $building) {
        $buildings[] = $building->buildingNumber;
        // Get building number and check to see if existing node exists.
        if (isset($existing_nodes) && $existing_nid = array_search($building->buildingNumber, $existing_nodes)) {
          // If existing, update values if different.
          $node = $storage->load($existing_nid);
          if ($node instanceof NodeInterface) {
            $changed = FALSE;
            if ($node->get('title')->value !== $building->buildingCommonName) {
              $node->set('title', $building->buildingCommonName);
              $changed = TRUE;
            }
            // There is at least one building with a blank space instead of
            // NULL for this value.
            // @todo Remove if FM can clean up their source.
            // https://github.com/uiowa/uiowa/issues/6084
            if ($building->buildingAbbreviation === '') {
              $building->buildingAbbreviation = NULL;
            }
            if ($node->get('field_building_abbreviation')->value !== $building->buildingAbbreviation) {
              $node->set('field_building_abbreviation', $building->buildingAbbreviation);
              $changed = TRUE;
            }

            if ($changed) {
              $node->setNewRevision(TRUE);
              $node->revision_log = 'Updated building from source';
              $node->setRevisionCreationTime(REQUEST_TIME);
              $node->setRevisionUserId(1);
              $node->save();
              $entities_updated++;
            }
          }
        }
        else {
          // If not, create new.
          $node = Node::create([
            'type' => 'building',
            'title' => $building->buildingCommonName,
            'field_building_number' => $building->buildingNumber,
            'field_building_abbreviation' => $building->buildingAbbreviation,
          ]);
          $node->enforceIsNew();
          $node->save();
          $entities_created++;
        }
      }

      // Loop through to remove nodes that no longer exist in API data.
      if ($entities) {
        foreach ($existing_nodes as $nid => $existing_node) {
          if (!in_array($existing_node, $buildings)) {
            $node = $storage->load($nid);
            $node->delete();
            $entities_deleted++;
          }
        }
      }
    }

    $arguments = [
      '@created' => $entities_created,
      '@updated' => $entities_updated,
      '@deleted' => $entities_deleted,
    ];
    $this->getLogger('facilities_core')->notice('@created buildings were created, @updated updated, @deleted deleted. That is neat.', $arguments);

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
