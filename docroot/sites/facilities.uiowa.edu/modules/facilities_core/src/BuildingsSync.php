<?php

namespace Drupal\facilities_core;

use Drupal\uiowa_core\EntitySyncAbstract;

/**
 * Sync building information.
 */
class BuildingsSync extends EntitySyncAbstract {

  /**
   * {@inheritdoc}
   */
  public string $bundle = 'building';

  /**
   * {@inheritdoc}
   */
  protected $fieldSyncKey = 'field_building_number';

  /**
   * {@inheritdoc}
   */
  protected $apiRecordSyncKey = 'buildingNumber';

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      // Request from Facilities API to get buildings. Add/update/remove.
      $facilities_api = \Drupal::service('uiowa_facilities.api');
      $this->data = $facilities_api->getBuildings();
    }
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  protected function processRecord(&$record) {
    // There is at least one building with a blank space instead of
    // NULL for this value.
    // @todo Remove if FM can clean up their source.
    // https://github.com/uiowa/uiowa/issues/6084
    if ($record->buildingAbbreviation === '') {
      $record->buildingAbbreviation = NULL;
    }

    // If the namedBuilding field is not NULL, it needs to be converted to a
    // entity ID for an existing named building.
    if (isset($record->namedBuilding)) {
      $record->namedBuilding = $this->findNamedBuildingNid($record->namedBuilding);
    }
  }

  protected function processEntity(&$entity, $record): bool {
    return BuildingProcessor::process($entity, $record);
  }

  /**
   * Find a named build node ID based on a first name and last name.
   *
   * @param string $string
   *   The string being searched.
   *
   * @return int|null
   *   The entity ID of the named building, if it exists.
   */
  protected function findNamedBuildingNid($string) {
    $names = explode(', ', trim($string));
    // If there are not two names, this won't work.
    if (count($names) === 2) {
      $nids = \Drupal::entityQuery('node')
        ->condition('type', 'named_building')
        ->condition('field_building_honoree_last_name', $names[0])
        ->condition('field_building_honoree_name', $names[1])
        ->execute();

      foreach ($nids as $nid) {
        return $nid;
      }
    }

    return NULL;
  }

}
