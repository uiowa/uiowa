<?php

namespace Drupal\facilities_core\Commands;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class FacilitiesCoreCommands extends DrushCommands {
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
   * @command facilities_core:buildings_import
   * @aliases fm-buildings
   * @usage facilities_core:buildings_import
   *  Ideally this is done as a crontab that is only run once a day.
   */
  public function importBuildings() {
    // Switch to the admin user to pass access check.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));

    // Establish some counts for message at the end.
    $entities_created = 0;
    $entities_updated = 0;
    $entities_deleted = 0;

    // Request from Facilities API to get buildings. Add/update/remove.
    $facilities_api = \Drupal::service('uiowa_facilities.api');
    $data = $facilities_api->getBuildings();

    // Get existing building nodes.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'building')
      ->accessCheck(TRUE);
    $entities = $query->execute();

    // Retrieve building number values from existing nodes.
    if ($entities) {
      $storage = \Drupal::getContainer()->get('entity_type.manager')->getStorage('node');
      $nodes = $storage->loadMultiple($entities);
      $existing_nodes = [];
      foreach ($nodes as $nid => $node) {
        if ($node instanceof FieldableEntityInterface) {
          if ($node->hasField('field_building_number') && !$node->get('field_building_number')->isEmpty()) {
            $existing_nodes[$nid] = $node->get('field_building_number')->value;
          }
        }
      }
    }

    if ($data) {
      $buildings = [];
      foreach ($data as $building) {
        $buildings[] = $building->buildingNumber;
        // Get building number and check to see if existing node exists.
        if (isset($existing_nodes) && $existing_nid = array_search($building->buildingNumber, $existing_nodes)) {
          // If existing, update values.
          $node = $storage->load($existing_nid);
          if ($node instanceof NodeInterface) {
            $node->set('title', $building->buildingCommonName);
            $node->set('field_building_number', $building->buildingNumber);
            $node->set('field_building_abbreviation', $building->buildingAbbreviation);
            $node->save();
            $entities_updated++;
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
