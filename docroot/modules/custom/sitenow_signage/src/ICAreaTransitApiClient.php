<?php

namespace Drupal\sitenow_signage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_core\ApiAuthKeyInterface;
use Drupal\uiowa_core\ApiAuthKeyTrait;
use Drupal\uiowa_core\ApiClientBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 *
 */
class ICAreaTransitApiClient extends ApiClientBase {

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

  /**
   * Get a list of bus stops.
   */
  public function getStopList(): array {
    $response = $this->get('stoplist');
    return $response->stops;
  }

}
