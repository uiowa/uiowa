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
   * A map of building numbers to node IDs.
   *
   * @var array
   */
  protected array $buildNumberNodeMap = [];

  /**
   * An array of nodes that exist and have been loaded keyed by node ID.
   *
   * @var NodeInterface[]
   */
  protected array $existingNodes = [];

  /**
   * @var array|null
   */
  protected ?array $data;

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   */
  public function __construct(AccountSwitcherInterface $accountSwitcher) {
    $this->accountSwitcher = $accountSwitcher;
  }

  protected function getData() {
    if (!isset($this->data)) {
      // Request from Facilities API to get buildings. Add/update/remove.
      $facilities_api = \Drupal::service('uiowa_facilities.api');
      $this->data = $facilities_api->getBuildings();
    }
    return $this->data;
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

    if (!$this->getData()) {
      // @todo Add a logging message that data was not able to be returned.
      return;
    }

    $buildings = [];

    $storage = \Drupal::getContainer()->get('entity_type.manager')->getStorage('node');

    // Get existing building nodes.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'building')
      ->accessCheck(TRUE);
    $entities = $query->execute();

    // Retrieve building number values from existing nodes.
    if ($entities) {
      $nodes = $storage->loadMultiple($entities);
      $this->existingNodes = [];
      foreach ($nodes as $nid => $node) {
        if ($node instanceof FieldableEntityInterface) {
          if ($node->hasField('field_building_number') && !$node->get('field_building_number')->isEmpty()) {
            $this->existingNodes[$nid] = $node;
            $this->buildNumberNodeMap[$node->get('field_building_number')->value] = $nid;
          }
        }
      }
    }

    $map = [
      'title' => 'buildingCommonName',
      'field_building_number' => 'buildingNumber',
      'field_building_abbreviation' => 'buildingAbbreviation',
      'field_building_address' => 'address',
      'field_building_area' => 'grossArea',
      'field_building_year_built' => 'yearBuilt',
      'field_building_ownership' => 'owned',
    ];

    foreach ($this->getData() as $building) {
      $buildings[] = $building->buildingNumber;

      // There is at least one building with a blank space instead of
      // NULL for this value.
      // @todo Remove if FM can clean up their source.
      // https://github.com/uiowa/uiowa/issues/6084
      if ($building->buildingAbbreviation === '') {
        $building->buildingAbbreviation = NULL;
      }
      $existing_nid = $this->buildNumberNodeMap[$building->buildingNumber] ?? NULL;
      $changed = FALSE;

      // Get building number and check to see if existing node exists.
      if (!is_null($existing_nid)) {
        // If existing, update values if different.
        $node = $this->existingNodes[$existing_nid] ?? $storage->load($existing_nid);
      }
      else {
        // If not, create new.
        $node = Node::create([
          'type' => 'building',
        ]);
      }

      if ($node instanceof NodeInterface) {
        foreach ($map as $to => $from) {
          // @todo Add a message if a node doesn't have a field.
          if ($node->hasField($to) && $node->get($to)->value !== $building->{$from}) {
            $node->set($to, $building->{$from});
            $changed = TRUE;
          }
        }

        if (!is_null($existing_nid)) {
          if ($changed) {
            $node->setNewRevision();
            $node->revision_log = 'Updated building from source';
            $node->setRevisionCreationTime(REQUEST_TIME);
            $node->setRevisionUserId(1);
            $node->save();
            $entities_updated++;
          }
        } else {
          $node->enforceIsNew();
          $node->save();
          $entities_created++;
        }
      }
    }

    // Loop through to remove nodes that no longer exist in API data.
    if ($entities) {
      foreach ($this->buildNumberNodeMap as $name => $nid) {
        if (!in_array($name, $buildings)) {
          $node = $this->existingNodes[$nid] ?? $storage->load($nid);
          $node->delete();
          $entities_deleted++;
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
