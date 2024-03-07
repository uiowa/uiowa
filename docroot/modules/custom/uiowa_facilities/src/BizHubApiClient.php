<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientBase;

/**
 * The BizHub API service.
 */
class BizHubApiClient extends ApiClientBase implements BizHubApiClientInterface {

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return $this->configFactory->get('uiowa_facilities.api_endpoints')->get('bizhub');
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase() {
    return 'uiowa_facilities_api_bizhub';
  }

  /**
   * Get all buildings.
   *
   * @return array
   *   The buildings object.
   */
  public function getBuildings() {
    return $this->get('buildings');
  }

  /**
   * Get single building by number.
   *
   * @return array
   *   The building object.
   */
  public function getBuilding($building_number) {
    return $this->get('building', [
      'query' => [
        'bldgnumber' => $building_number,
      ],
    ]);
  }

  /**
   * Get building coordinators by building number.
   *
   * @return array
   *   The building coordinators object.
   */
  public function getBuildingCoordinators($building_number) {
    $data = $this->get('bldgCoordinators');
    $contact = [];

    foreach ($data as $d) {
      if ($building_number === $d->buildingNumber) {
        $contact = $d;
      }
    }

    return $contact;
  }

}
