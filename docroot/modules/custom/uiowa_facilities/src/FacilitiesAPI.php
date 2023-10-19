<?php

namespace Drupal\uiowa_facilities;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Facilities API service.
 */
class FacilitiesAPI {

  const BASE = 'https://bizhub.facilities.uiowa.edu/bizhub/ext/';

  /**
   * The uiowa_facilities logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * The uiowa_facilities cache.
   */
  protected CacheBackendInterface $cache;

  /**
   * The HTTP client.
   */
  protected ClientInterface $client;

  /**
   * Constructs a FM object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The uiowa_facilities logger channel.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The uiowa_facilities cache.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(LoggerInterface $logger, CacheBackendInterface $cache, ClientInterface $http_client) {
    $this->logger = $logger;
    $this->cache = $cache;
    $this->client = $http_client;
  }

  /**
   * Make a Facilities API request and return data.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $path
   *   The API path to use. Do not include the base URL.
   * @param array $params
   *   Optional request parameters.
   * @param array $options
   *   Optional request options. All requests expect JSON response data.
   *
   * @return mixed
   *   The API response data.
   */
  public function request(string $method, string $path, array $params = [], array $options = []): mixed {
    // Encode any special characters and trim duplicate slash.
    $path = UrlHelper::encodePath($path);
    $uri = self::BASE . ltrim($path, '/');

    // Append any query string parameters.
    if (!empty($params)) {
      $query = UrlHelper::buildQuery($params);
      $uri .= "?{$query}";
    }

    // Merge additional options with default but allow overriding.
    $options = array_merge([
      'headers' => [
        'Accept' => 'application/json',
      ],
    ], $options);

    // Create a hash for the CID. Can always be decoded for debugging purposes.
    $hash = base64_encode($uri . serialize($options));
    $cid = "uiowa_facilities:request:{$hash}";
    // Default $data to FALSE in case of API fetch failure.
    $data = FALSE;

    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      try {
        $response = $this->client->request($method, $uri, $options);
      }
      catch (RequestException | GuzzleException $e) {
        $this->logger->error('Error encountered getting data from @endpoint: @code @error', [
          '@endpoint' => $uri,
          '@code' => $e->getCode(),
          '@error' => $e->getMessage(),
        ]);
      }

      if (isset($response)) {
        $contents = $response->getBody()->getContents();

        /** @var object $data */
        $data = json_decode($contents);

        // Cache for 15 minutes.
        $this->cache->set($cid, $data, time() + 900);
      }
    }

    return $data;
  }

  /**
   * Get all buildings.
   *
   * @return array
   *   The buildings object.
   */
  public function getBuildings(): array {
    return $this->request('GET', 'buildings');
  }

  /**
   * Get single building by number.
   *
   * @return array
   *   The building object.
   */
  public function getBuilding($building_number): array {
    return $this->request('GET', 'building', [
      'bldgnumber' => $building_number,
    ]);
  }

  /**
   * Get building coordinators by building number.
   *
   * @return array
   *   The building coordinators object.
   */
  public function getBuildingCoordinators($building_number): array {
    $data = $this->request('GET', 'bldgCoordinators');
    $contact = [];

    foreach ($data as $d) {
      if ($building_number === $d->buildingNumber) {
        $contact = $d;
      }
    }

    return $contact;
  }

}
