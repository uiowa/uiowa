<?php

namespace Drupal\sitenow_signage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_core\ApiAuthKeyInterface;
use Drupal\uiowa_core\ApiAuthKeyTrait;
use Drupal\uiowa_core\ApiClientBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 *
 */
class ICAreaTransitApiClient extends ApiClientBase implements ApiAuthKeyInterface {
  use ApiAuthKeyTrait;

  /**
   * Constructs a DispatchApiClient object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory object.
   */
  public function __construct(protected ClientInterface $client, protected LoggerInterface $logger, protected CacheBackendInterface $cache, protected ConfigFactoryInterface $configFactory) {
    parent::__construct($client, $logger, $cache, $configFactory);
    $this->setKey($this->configFactory->get('sitenow_signage.settings')->get('icareatransit_api_key') ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return 'https://api.icareatransit.org/';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase() {
    return 'sitenow_signage_icareatransit';
  }

}
