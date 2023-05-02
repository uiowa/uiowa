<?php

namespace Drupal\facilities_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\uiowa_core\EntityProcessorBase;

/**
 * Sync building information.
 */
class BuildingsProcessor extends EntityProcessorBase {

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
      // Request from Facilities API to get buildings.
      $facilities_api = \Drupal::service('uiowa_facilities.api');
      $this->data = $facilities_api->getBuildings();
    }
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  protected function processRecord(&$record) {
    if (!is_null($building_number = $record?->{$this->apiRecordSyncKey})) {
      // Request from Facilities API to get buildings.
      $facilities_api = \Drupal::service('uiowa_facilities.api');
      $result = $facilities_api->getBuilding($building_number);
      foreach ((array) $result as $key => $value) {
        $record->{$key} = $value;
      }
    }

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
      $record->namedBuilding = $this->findNamedBuildingNid($record->{$this->apiRecordSyncKey});
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    return BuildingItemProcessor::process($entity, $record);
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
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'named_building')
      ->condition('field_building_building_id', $string)
      ->execute();

    foreach ($nids as $nid) {
      return $nid;
    }

    return NULL;
  }

}
