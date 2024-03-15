<?php

namespace Drupal\classrooms_core\Commands;

use Drupal\classrooms_core\Entity\Building;
use Drupal\classrooms_core\RoomItemProcessor;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
  use StringTranslationTrait;

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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(AccountSwitcherInterface $accountSwitcher, MauiApi $mauiApi, CacheBackendInterface $mauiCache, EntityTypeManagerInterface $entityTypeManager, TimeInterface $time) {
    $this->accountSwitcher = $accountSwitcher;
    $this->mauiApi = $mauiApi;
    $this->mauiCache = $mauiCache;
    $this->entityTypeManager = $entityTypeManager;
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
      // Request from MAUI API. We're interested
      // in both university and programmed classrooms.
      $buildings = [];
      foreach (['UNIVERSITY_CLASSROOM', 'PROGRAMMED_CLASSROOM'] as $filter) {
        $data = $this->mauiApi->getClassroomsData($filter);
        foreach ($data as $room) {
          $buildings[strtolower($room->facilityBuilding?->building)] = [
            'name' => $room->facilityBuilding?->buildingDescr,
            'number' => $room->facilityBuilding?->buildingNumber,
          ];
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

      foreach ($buildings as $building_id => $building_info) {
        if (!in_array($building_id, $entities)) {
          $building = Building::create([
            'id' => $building_id,
            'label' => $building_info['name'],
            'status' => 1,
            'number' => $building_info['number'],
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
      $this->logger()->notice('@count buildings were created. That is neat.', $arguments);
    }
    else {
      $this->getLogger('classrooms_core')->notice('Bummer. No new buildings were created. Maybe next time.');
      $this->logger()->notice('Bummer. No new buildings were created. Maybe next time.');
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

  /**
   * Triggers the classrooms rooms update.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command classrooms_core:rooms_import
   *
   * @option batch The batch size
   * @aliases classrooms-rooms
   * @usage classrooms_core:rooms_import
   *  Ideally this is done as a crontab that is only run once a day.
   * @usage classrooms_core:rooms_import --batch=20
   *  Process rooms with a specified batch size.
   */
  public function importRooms(array $options = ['batch' => 20]) {
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));

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
      $this->logger()->notice('No rooms available to update.');

      // Switch user back.
      $this->accountSwitcher->switchBack();
      return;
    }

    // Retrieve building and room number values from existing nodes.
    $storage = $this->entityTypeManager->getStorage('node');
    $room_processor = new RoomItemProcessor();

    // Batch them up.
    // Create the operations array for the batch.
    $operations = [];
    $num_operations = 0;
    $batch_id = 1;
    // Quick manipulate to ensure we have a positve
    // integer to use for the batch size.
    $batch_size = max(1, abs((int) $options['batch']));
    for ($i = 0; $i < count($entities);) {
      $nids = $storage
        ->getQuery()
        ->condition('type', 'room')
        ->range($i, $batch_size)
        ->accessCheck()
        ->execute();
      $nodes = $storage->loadMultiple($nids);

      $operations[] = [
        '\Drupal\classrooms_core\BatchRooms::processNode',
        [
          $batch_id,
          $nodes,
          $room_processor,
        ],
      ];
      $batch_id++;
      $num_operations++;
      $i += $batch_size;
    }
    $batch = [
      'title' => $this->t('Checking @num node(s) for updates.', [
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
    $this->logger()->notice('Update batch operations ended.');

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
