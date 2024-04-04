<?php

namespace Drupal\uiowa_facilities;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\uiowa_core\ApiAuthBasicTrait;
use Drupal\uiowa_core\ApiClientBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * The BuildUI API service.
 */
class BuildUiApiClient extends ApiClientBase implements BuildUiApiClientInterface {

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
    $auth = $this->configFactory->get('uiowa_facilities.apis')->get('buildui.auth');
    $this->username = $auth['user'] ?? NULL;
    $this->password = $auth['pass'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return $this->configFactory->get('uiowa_facilities.apis')->get('buildui.endpoint');
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase() {
    return 'uiowa_facilities_api_buildui';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectsByBuilding($building_number): mixed {
    return $this->get('projects', [
      'query' => [
        'bldgnumber' => $building_number,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFeaturedProjects(): array|bool {
    uiowa_core_logger_log('Retrieving featured projects', 'uiowa_facilities', RfcLogLevel::INFO);
    return $this->get('featuredprojects');
  }

  /**
   * {@inheritdoc}
   */
  public function getCapitalProjects(): array|bool {
    $this->logger->info('Retrieving capital projects');
    return $this->get('capitalprojects');
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectInfo(string $project_id): mixed {
    return $this->get('projectinfo', [
      'query' => [
        'projnumber' => $project_id,
      ],
    ]);
  }

}
