<?php

namespace Drupal\uiowa_facilities;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Facilities API service.
 */
class FacilitiesAPI {

  const BASE_URL_1 = 'https://bizhub.facilities.uiowa.edu/bizhub/ext/';
  const BASE_URL_2 = 'https://buildui.facilities.uiowa.edu/buildui/ext/';

  /**
   * The uiowa_facilities logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The uiowa_facilities cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

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
   * @param string $base
   *   The base URL to use for the request. Defaults to self::BASE_URL_1.
   *
   * @return mixed
   *   The API response data.
   */
  public function request($method, $path, array $params = [], array $options = [], $base = self::BASE_URL_1) {
    // Encode any special characters and trim duplicate slash.
    $path = UrlHelper::encodePath($path);
    $uri = $base . ltrim($path, '/');

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
  public function getBuildings() {
    return $this->request('GET', 'buildings');
  }

  /**
   * Get single building by number.
   *
   * @return array
   *   The building object.
   */
  public function getBuilding($building_number) {
    return $this->request('GET', 'building', [
      'bldgnumber' => $building_number,
    ]);
  }

  /**
   * Get all 'field_building_number' from the 'building' content type.
   *
   * @return array
   *   The array of building numbers.
   */
  public function getAllBuildingNumbers() {
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'building')
    // Add this line.
      ->accessCheck(FALSE);
    $nids = $query->execute();

    $building_numbers = [];
    foreach ($nids as $nid) {
      $node = Node::load($nid);
      $field_building_number = $node->get('field_building_number')->value;
      $building_numbers[] = $field_building_number;
    }

    return $building_numbers;
  }

  /**
   * Get all projects.
   *
   * @return array
   *   The projects object.
   */
  public function getProjects() {
    $building_numbers = $this->getAllBuildingNumbers();

    $projects = [];
    foreach ($building_numbers as $number) {
      // Use each number to make a query.
      $response = $this->request('GET', 'projects', ['bldgnumber' => $number], [], self::BASE_URL_2);

      // Check if the response array is not empty.
      if (!empty($response)) {
        // If the response contains multiple arrays, loop through each of them.
        foreach ($response as $project) {
          // Add the project to the projects array.
          $projects[] = $project;
        }
      }
    }

    // Return the array of projects.
    return $projects;
  }

  /**
   * Get building coordinators by building number.
   *
   * @return array
   *   The building coordinators object.
   */
  public function getBuildingCoordinators($building_number) {
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
