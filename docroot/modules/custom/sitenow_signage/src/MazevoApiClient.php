<?php

namespace Drupal\sitenow_signage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_core\ApiAuthKeyTrait;
use Drupal\uiowa_core\ApiClientBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * The Mazevo API service.
 */
class MazevoApiClient extends ApiClientBase implements MazevoApiClientInterface {

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
    $this->setKey($this->configFactory->get('sitenow_signage.settings')->get('mazevo_api_key') ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return 'https://api-east.mymazevo.com/api/';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase() {
    return 'sitenow_signage_api_mazevo';
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents(array $body = []): \stdClass|bool {
    $json = json_decode('{}');
    if ($body) {
      $json = $body;
    }
    $options = [
      'json' => $json,
      'cache_length' => 86400,
    ];
    $result = $this->post('PublicEvent/getevents', $options);

    // Only cast to an object if the call succeeded.
    $result = $result === FALSE ? $result : (object) $result;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getRooms($building_id = NULL) {
    $json = json_decode('{}');
    if ($building_id) {
      $json = [
        'buildingId' => $building_id,
      ];
    }
    $options = [
      'json' => $json,
    ];
    $result = $this->post('PublicConfiguration/Rooms', $options);

    // Only decode if the call succeeded.
    $result = !$result ? $result : json_decode(json_encode($result), TRUE);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getEventTypes() {
    $data = $this->get('PublicConfiguration/EventTypes', []);
    return json_decode(json_encode($data), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function addAuthToOptions(array &$options = []): void {
    if (!is_null($this->apiKey)) {
      // Merge additional options with default but allow overriding.
      $options = array_merge([
        'headers' => [
          'x-api-key' => $this->apiKey,
        ],
      ], $options);
    }
  }

}
