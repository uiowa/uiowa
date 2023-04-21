<?php

namespace Drupal\classrooms_core\Commands;

use Drupal\classrooms_core\Entity\Building;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\node\NodeInterface;
use Drupal\uiowa_maui\MauiApi;
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
   * The uiowa_maui.api service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $mauiApi;

  /**
   * The cache.uiowa_maui service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $mauiCache;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   * @param \Drupal\uiowa_maui\MauiApi $mauiApi
   *   The uiowa_maui.api service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $mauiCache
   *   The cache.uiowa_maui service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity_type.manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(AccountSwitcherInterface $accountSwitcher, MauiApi $mauiApi, CacheBackendInterface $mauiCache, EntityTypeManagerInterface $entityTypeManager, Connection $connection, TimeInterface $time) {
    $this->accountSwitcher = $accountSwitcher;
    $this->mauiApi = $mauiApi;
    $this->mauiCache = $mauiCache;
    $this->entityTypeManager = $entityTypeManager;
    $this->connection = $connection;
    $this->time = $time;
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
    if ($cached = $this->mauiCache->get($cid)) {
      $buildings = $cached->data;
    }
    else {
      // Request from MAUI API
      // and then filter based on Classroom's requirements.
      $data = $this->mauiApi->getClassroomsData();
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
      $request_time = $this->time->getRequestTime();
      $this->mauiCache->set($cid, $buildings, $request_time + 21600);
    }

    if (!empty($buildings)) {
      // Check for existing building config entities before creating.
      $query = $this->entityTypeManager
        ->getStorage('building')
        ->getQuery()
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
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
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
    $storage = $this->entityTypeManager->getStorage('node');
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
      $data = $this->mauiApi->getRoomData($info['building_id'], $info['room_id']);

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
          $term = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->loadByProperties([
              'name' => $data[0]->roomType,
              'vid' => 'room_types',
            ]);
          if (empty($term) || (int) $node->get('field_room_type')->getString() !== array_key_first($term)) {
            $this->nodeSaveHelper($node);
            $entities_updated++;
            continue;
          }
        }

        // Mapping the Responsible Unit field to the
        // acadOrgUnitName value from endpoint.
        if ($node->hasField('field_room_responsible_unit') && isset($data[0]->acadOrgUnitName)) {
          // Returns all terms matching name within vocabulary.
          $term = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->loadByProperties([
              'name' => $data[0]->acadOrgUnitName,
              'vid' => 'units',
            ]);
          if (empty($term) || (int) $node->get('field_room_responsible_unit')->getString() !== array_key_first($term)) {
            $this->nodeSaveHelper($node);
            $entities_updated++;
            continue;
          }
        }

        // Mapping Room Features and Technology Features fields
        // to the featureList value from endpoint.
        if (isset($data[0]->featureList)) {
          $query = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->getQuery()
            ->orConditionGroup()
            ->condition('vid', 'room_features')
            ->condition('vid', 'technology_features');

          $tids = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->getQuery()
            ->condition($query)
            ->execute();
          if ($tids) {
            $storage = $this->entityTypeManager->getStorage('taxonomy_term');
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
              $node_features = $node->get('field_room_features')->getString();
              $node_features = explode(', ', $node_features);
              if ($node_features !== $room_features) {
                $this->nodeSaveHelper($node);
                $entities_updated++;
                continue;
              }
            }
            if (!empty($tech_features)) {
              $node_tech_features = $node->get('field_room_technology_features')->getString();
              $node_tech_features = explode(', ', $node_tech_features);
              if ($node_tech_features !== $tech_features) {
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
          $query = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->getQuery()
            ->condition('vid', 'scheduling_regions')
            ->execute();

          if ($query) {
            $storage = $this->entityTypeManager->getStorage('taxonomy_term');
            $terms = $storage->loadMultiple($query);
            foreach ($terms as $term) {
              if ($api_mapping = $term->get('field_api_mapping')?->value) {
                if (in_array($api_mapping, $data[0]->regionList)) {
                  if ($node->get('field_room_scheduling_regions')->getString() !== $term->id()) {
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
    $node->setRevisionCreationTime($this->time->getRequestTime());
    $node->setRevisionUserId(1);
    $node->save();
  }

}
