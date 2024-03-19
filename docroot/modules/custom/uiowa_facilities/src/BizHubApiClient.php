<?php

namespace Drupal\uiowa_facilities;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_core\ApiAuthBasicTrait;
use Drupal\uiowa_core\ApiClientBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * The BizHub API service.
 */
class BizHubApiClient extends ApiClientBase implements BizHubApiClientInterface {

  use ApiAuthBasicTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ClientInterface $client,
    LoggerInterface $logger,
    CacheBackendInterface $cache,
    ConfigFactoryInterface $configFactory
  ) {
    parent::__construct($client, $logger, $cache, $configFactory);
    $auth = $this->configFactory->get('uiowa_facilities.apis')->get('bizhub.auth');
    $this->username = $auth['username'] ?? NULL;
    $this->password = $auth['password'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return $this->configFactory->get('uiowa_facilities.apis')->get('bizhub.endpoint');
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
  public function getBuildingCoordinators(): array {
    return $this->get('bldgCoordinators');
  }

}
