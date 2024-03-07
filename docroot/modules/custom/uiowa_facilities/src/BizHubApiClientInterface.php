<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A BizHub API client interface.
 */
interface BizHubApiClientInterface extends ApiClientInterface {

  /**
   * Get all buildings.
   *
   * @return array
   *   The buildings object.
   */
  public function getBuildings(): array;

  /**
   * Get single building by number.
   *
   * @return array
   *   The building object.
   */
  public function getBuilding($building_number): array;

  /**
   * Get building coordinators by building number.
   *
   * @return array
   *   The building coordinators object.
   */
  public function getBuildingCoordinators(): array;

}
