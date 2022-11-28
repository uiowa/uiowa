<?php

namespace Drupal\classrooms_core\Commands;

use Drupal\classrooms_core\Entity\Building;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
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

    $cid = "uiowa_maui:request:buildings_filtered";
    if ($cached = \Drupal::cache('uiowa_maui')->get($cid)) {
      $buildings = $cached->data;
    }
    else {
      // Request from MAUI API and then filter based on Classroom's requirements.
      $maui_api = \Drupal::service('uiowa_maui.api');
      $data = $maui_api->getClassroomsData();
      $buildings = [];
      $filters = [
        "1) University Classrooms - Level 1",
        "1) University Classrooms - Original",
        "1) University Classrooms",
        "1) University Classrooms - Study Space",
        "1) Programmed Classrooms - Level 2",
        "Classroom-Programmed",
      ];

      foreach ($data as $room) {
        $category = array_intersect($filters, $room->regionList);

        if ($category) {
          $buildings[$room->buildingCode] = [
            "building_id" => $room->buildingCode,
            "building_name" => $room->buildingName,
          ];
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

      foreach ($buildings as $building) {
        if (!in_array($building, $entities)) {
          $building = Building::create([
            'id' => $building['building_id'],
            'label' => ucwords(strtolower($building['building_name'])),
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

}
