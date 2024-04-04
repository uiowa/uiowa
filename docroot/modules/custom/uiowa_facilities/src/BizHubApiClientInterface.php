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
   * @return array|bool
   *   The buildings object.
   */
  public function getBuildings(): array|bool;

  /**
   * Get single building by number.
   *
   * @return array|null
   *   The building object.
   */
  public function getBuilding($building_number): array|bool;

  /**
   * Get building coordinators by building number.
   *
   * @return array|null
   *   The building coordinators object.
   */
  public function getBuildingCoordinators(): array|bool;

}
