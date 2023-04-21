<?php

namespace Drupal\classrooms_core\Commands;

use Drupal\classrooms_core\Entity\Building;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\node\NodeInterface;
use Drush\Commands\DrushCommands;

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
      // Request from MAUI API
      // and then filter based on Classroom's requirements.
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
          !$node->get('field_room_building_id')->isEmpty() &&
          $node->hasField('field_room_room_id') &&
          !$node->get('field_room_room_id')->isEmpty()
        ) {
          $existing_nodes[$nid] = [
            'building_id' => $node->get('field_room_building_id')?->get(0)?->getValue()['target_id'],
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

      // If existing, update values if different.
      $node = $storage->load($nid);
      if ($node instanceof NodeInterface) {

        // Mapping the Max Occupancy field
        // to the maxOccupancy value from endpoint.
        if ($node->hasField('field_room_max_occupancy') && isset($data[0]->maxOccupancy)) {
          if (filter_var($data[0]->maxOccupancy, FILTER_VALIDATE_INT) !== FALSE) {
            if ($node->get('field_room_max_occupancy')->value !== $data[0]->maxOccupancy) {
              $this->nodeSaveHelper($node);
              $entities_updated++;
              continue;
            }
          }
        }

        // Mapping the Room Name field to the roomName value from endpoint.
        if ($node->hasField('field_room_name') && isset($data[0]->roomName)) {
          if (strlen($data[0]->roomName) > 1) {
            if ($node->get('field_room_name')->value !== $data[0]->roomName) {
              $this->nodeSaveHelper($node);
              $entities_updated++;
              continue;
            }
          }
        }

        // Mapping the Instructional Room Category field to the
        // roomCategory value from endpoint.
        if ($node->hasField('field_room_instruction_category') && isset($data[0]->roomCategory)) {
          $field_definition = $node->getFieldDefinition('field_room_instruction_category')->getFieldStorageDefinition();
          $field_allowed_options = options_allowed_values($field_definition, $node);
          if (array_key_exists($data[0]->roomCategory, $field_allowed_options)) {
            if ($node->get('field_room_instruction_category')->value !== $data[0]->roomCategory) {
              $this->nodeSaveHelper($node);
              $entities_updated++;
              continue;
            }
          }
        }

        // Mapping the Room Type field to the roomType value from endpoint.
        if ($node->hasField('field_room_type') && isset($data[0]->roomType)) {
          // Returns all terms matching name within vocabulary.
          $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties([
              'name' => $data[0]->roomType,
              'vid' => 'room_types',
            ]);
          if (empty($term) || $node->get('field_room_type')->get(0)->getValue()['target_id'] !== array_key_first($term)) {
            $this->nodeSaveHelper($node);
            $entities_updated++;
            continue;
          }
        }

        // Mapping the Responsible Unit field to the
        // acadOrgUnitName value from endpoint.
        if ($node->hasField('field_room_responsible_unit') && isset($data[0]->acadOrgUnitName)) {
          // Returns all terms matching name within vocabulary.
          $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties([
              'name' => $data[0]->acadOrgUnitName,
              'vid' => 'units',
            ]);
          if (empty($term) || $node->get('field_room_responsible_unit')->get(0)->getValue()['target_id'] !== array_key_first($term)) {
            $this->nodeSaveHelper($node);
            $entities_updated++;
            continue;
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
              if ($node->get('field_room_features')->value !== $room_features) {
                $this->nodeSaveHelper($node);
                $entities_updated++;
                continue;
              }
            }
            if (!empty($tech_features)) {
              if ($node->get('field_room_technology_features')->value !== $tech_features) {
                $this->nodeSaveHelper($node);
                $entities_updated++;
                continue;
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
                  if ($node->get('field_room_scheduling_regions')->value !== $term->id()) {
                    $this->nodeSaveHelper($node);
                    $entities_updated++;
                  }
                }
              }
            }
          }
        }

      }
    }

    $this->getLogger('classrooms_core')->notice('@updated rooms updated. That is neat.', [
      '@updated' => $entities_updated,
    ]);

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

  /**
   * Helper to set revisions and save a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be saved.
   */
  protected function nodeSaveHelper($node) {
    $node->setNewRevision(TRUE);
    $node->revision_log = 'Updated room from source';
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->setRevisionUserId(1);
    $node->save();
  }

}
