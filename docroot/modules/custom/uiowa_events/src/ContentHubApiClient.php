<?php

namespace Drupal\uiowa_events;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_core\ApiClientBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * The Content Hub API service.
 */
class ContentHubApiClient extends ApiClientBase implements ContentHubApiClientInterface {

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
    $this->username = $auth['user'] ?? NULL;
    $this->password = $auth['pass'] ?? NULL;
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
  public function getEvents(): array|bool {
    return $this->get('events');
  }

}
