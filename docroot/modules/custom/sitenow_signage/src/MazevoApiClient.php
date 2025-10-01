<?php

namespace Drupal\sitenow_signage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_core\ApiAuthKeyTrait;
use Drupal\uiowa_core\ApiClientBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * The Content Hub API service.
 */
class MazevoApiClient extends ApiClientBase implements MazevoApiClientInterface {

  use ApiAuthKeyTrait;

  /**
   * {@inheritdoc }
   */
  protected function headerParameterName(): string {
    return 'x-api-key';
  }

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
    return 'sitenow_signage_api';
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents(array $options = []): \stdClass|bool {
    return $this->get('views/events_api.json', $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters($display_id) {
    $options = [
      'query' => [
        'display_id' => $display_id,
      ],
    ];
    return json_decode(json_encode($this->get('views/filters_api.json', $options)), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getBuildings($display_id) {
    $options = [
      'query' => [
        'display_id' => $display_id,
      ],
    ];
    return json_decode(json_encode($this->get('views/filters_api.json', $options)), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getRooms($building_id) {
    $options = [
      'json' => [
        'buildingId' => $building_id,
        ],
    ];
    $data = $this->request('POST', 'PublicConfiguration/Rooms', $options, 'json');
    return json_decode(json_encode($data), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getPlaces() {
    $options = [
      'query' => [
        'display_id' => 'places',
      ],
    ];
    return json_decode(json_encode($this->get('views/places_api.json', $options)), TRUE);
  }

}
