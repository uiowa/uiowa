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
  }

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return 'https://content.uiowa.edu/api/v1/';
    return 'https://content.uiowa.edu/api/v1/views/events_api.json?display_id=events&filters[enddate][value][date]=01-01-2100&filters[types]=355&filters[department]=7266&items_per_page=100';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase() {
    return 'uiowa_events_api_content_hub';
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents(array $options = []): \stdClass|bool {
    return $this->get('views/events_api.json', $options);
  }

}
