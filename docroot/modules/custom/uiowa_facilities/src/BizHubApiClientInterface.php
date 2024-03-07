<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A BizHub API client interface.
 */
interface BizHubApiClientInterface extends ApiClientInterface {

  /**
   * Return a list of buildings.
   */
  public function getBuildings();

  /**
   * Return a building.
   */
  public function getBuilding($building_number);

  /**
   * Return a list of building coordinators.
   */
  public function getBuildingCoordinators($building_number);

}
