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
   * {@inheritdoc}
   */
  public function getBuildings(): array {
    return $this->get('buildings');
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilding($building_number): array {
    return $this->get('building', [
      'query' => [
        'bldgnumber' => $building_number,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getBuildingCoordinators($building_number): array {
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
