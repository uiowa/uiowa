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
    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'room')
      ->execute();

    // If we don't have any entities, send a message
    // and we're done.
    if (empty($entities)) {
      $this->getLogger('classrooms_core')->notice('No rooms available to update.');

      // Switch user back.
      $this->accountSwitcher->switchBack();
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');

    // Create the operations array for the batch.
    $operations = [];
    $num_operations = 0;
    $batch_id = 1;
    for ($i = 0; $i < count($entities);) {
      $nids = $storage
        ->getQuery()
        ->condition('type', 'room')
        ->range($i, 50)
        ->execute();

      $nodes = $storage->loadMultiple($nids);

      $operations[] = [
        '\Drupal\classrooms_core\BatchRooms::processNode',
        [
          $batch_id,
          $nodes,
        ],
      ];
      $batch_id++;
      $num_operations++;
      $i += 50;
    }

    // 4. Create the batch.
    $batch = [
      'title' => t('Checking @num node(s) for updates.', [
        '@num' => $num_operations,
        ]),
      'operations' => $operations,
      'finished' => '\Drupal\classrooms_core\BatchRooms::processNodeFinished',
    ];

    // 5. Add batch operations as new batch sets.
    batch_set($batch);
    // 6. Process the batch sets.
    drush_backend_batch_process();
    // 7. Log some information.
    $this->getLogger('classrooms_core')->notice('Update batch operations ended.');

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
