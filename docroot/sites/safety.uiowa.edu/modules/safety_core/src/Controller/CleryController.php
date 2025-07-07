<?php

namespace Drupal\safety_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Clery Edge API operations.
 */
class CleryController extends ControllerBase {
  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The base URL for the Clery Edge API.
   */
  const BASE_URL = "https://app-cleryedge-api-prod.azurewebsites.net/api/public";

  /**
   * Constructs a new CleryController instance.
   */
  public function __construct(
    Client $http_client,
    ConfigFactoryInterface $config_factory,
    CacheBackendInterface $cache_backend,
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * Creates an instance of the controller.
   */
  public static function create(ContainerInterface $container) {
    return new static(
    $container->get("http_client"),
    $container->get("config.factory"),
    $container->get("cache.default")
    );
  }

  /**
   * Gets the API key from configuration.
   */
  protected function getApiKey() {
    return $this->configFactory
      ->get("safety_core.settings")
      ->get("clery_api.api_key") ?:
      "";
  }

  /**
   * Base method for making API requests with consistent setup.
   *
   * @param string $endpoint
   *   The API endpoint (without base URL).
   * @param string $method
   *   HTTP method (GET, POST, etc.).
   * @param array $options
   *   Additional request options.
   *
   * @return array
   *   Decoded JSON response.
   *
   * @throws \Exception
   */
  protected function makeApiRequest($endpoint, $method = 'GET', array $options = []) {
    $api_key = $this->getApiKey();
    if (empty($api_key)) {
      throw new \Exception("API key not configured");
    }

    // Default request configuration.
    $default_options = [
      'headers' => [
        'x-api-key' => $api_key,
        'Accept' => 'application/json',
      ],
      'timeout' => 30,
    ];

    // Merge with provided options.
    $request_options = array_merge_recursive($default_options, $options);

    // Make the request.
    $response = $this->httpClient->request($method, self::BASE_URL . $endpoint, $request_options);

    // Check response status.
    $valid_statuses = $options['valid_statuses'] ?? [200];
    if (!in_array($response->getStatusCode(), $valid_statuses)) {
      $error_body = $response->getBody()->getContents();
      throw new \Exception(
      "API request failed with status: " . $response->getStatusCode() .
      " for endpoint: " . $endpoint .
      ". Response: " . $error_body
      );
    }

    // Get response body content.
    $response_body = $response->getBody()->getContents();

    // Handle empty response body.
    if (empty($response_body)) {
      return [];
    }

    // Decode and validate response.
    $data = json_decode($response_body, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON response for endpoint: " . $endpoint);
    }

    return $data;
  }

  /**
   * Make a GET request with query parameters.
   */
  protected function apiGet($endpoint, array $query_params = []) {
    $options = [];
    if (!empty($query_params)) {
      $options['query'] = $query_params;
    }
    return $this->makeApiRequest($endpoint, 'GET', $options);
  }

  /**
   * Make a POST request with JSON data.
   */
  protected function apiPost($endpoint, array $data = []) {
    return $this->makeApiRequest($endpoint, 'POST', [
      'headers' => ['Content-Type' => 'application/json'],
      'json' => $data,
      'valid_statuses' => [200, 201],
    ]);
  }

  /**
   * Gets cached crime data or fetches from API using bucket system.
   */
  public function getCrimeData($start_date, $end_date, $limit = NULL) {
    // Create cache bucket.
    $bucket = (new \DateTime($end_date))->format("Y-m");
    $cid = "safety_core:crime_log:bucket:" . $bucket;

    // Check cache first.
    if ($cache = $this->cacheBackend->get($cid)) {
      $cached_data = $cache->data;
    }
    else {
      // Fetch date range for bucket.
      $bucket_start = (new \DateTime($bucket . "-01"))
        ->modify("-30 days")
        ->format("Y-m-d");
      $bucket_end = (new \DateTime($bucket . "-01"))
        ->modify("last day of this month")
        ->format("Y-m-d");

      $cached_data = $this->fetchCrimeData($bucket_start, $bucket_end);

      // Cache for 24 hours.
      $this->cacheBackend->set($cid, $cached_data, time() + 86400);
    }

    // Filter cached data to requested range.
    $filtered_data = $this->filterByDateRange(
    $cached_data,
    $start_date,
    $end_date
    );

    return $limit ? array_slice($filtered_data, 0, $limit) : $filtered_data;
  }

  /**
   * Filters crime data by date range.
   *
   * @throws \Exception
   */
  protected function filterByDateRange($data, $start_date, $end_date) {
    $start_timestamp = strtotime($start_date);
    $end_timestamp = strtotime($end_date . " 23:59:59");

    return array_filter($data, function ($crime) use (
      $start_timestamp,
      $end_timestamp
    ) {
      $crime_date = $crime["dateOffenseReported"] ?? "";
      if (empty($crime_date)) {
        return FALSE;
      }

      // Check if there's a time.
      if (preg_match("/\d{1,2}\/\d{1,2}\/\d{4} \d{1,2}:\d{2}/", $crime_date)) {
        $crime_timestamp = \DateTime::createFromFormat(
        "m/d/Y H:i",
        $crime_date
        );
      }
      elseif (preg_match("/\d{1,2}\/\d{1,2}\/\d{4}/", $crime_date)) {
        $crime_timestamp = \DateTime::createFromFormat("m/d/Y", $crime_date);
      }
      else {
        $crime_timestamp = new \DateTime($crime_date);
      }

      $crime_timestamp = $crime_timestamp
      ? $crime_timestamp->getTimestamp()
      : 0;

      return $crime_timestamp >= $start_timestamp &&
      $crime_timestamp <= $end_timestamp;
    });
  }

  /**
   * Fetches crime data from the API.
   */
  protected function fetchCrimeData($start_date, $end_date) {
    $query_params = [
      'FromDateReported' => $start_date,
      'ToDateReported' => $end_date,
    ];

    $data = $this->apiGet('/report/crime-log', $query_params);

    if (!is_array($data)) {
      throw new \Exception("Invalid API response format");
    }

    // Sort by newest first.
    usort($data, function ($a, $b) {
      $datetime_a =
      ($a["dateOffenseReported"] ?? "") .
      " " .
      ($a["timeOffenseReported"] ?? "00:00:00");
      $datetime_b =
      ($b["dateOffenseReported"] ?? "") .
      " " .
      ($b["timeOffenseReported"] ?? "00:00:00");

      return strtotime($datetime_b) - strtotime($datetime_a);
    });

    return $this->formatCrimeDates($data);
  }

  /**
   * Formats date fields in crime data array.
   */
  protected function formatCrimeDates(array $crimes) {
    foreach ($crimes as &$crime) {
      // Format occurred date.
      $start =
        $crime["dateOffenseOccuredStart"] ??
        ($crime["dateOffenseOccured"] ?? NULL);
      $end = $crime["dateOffenseOccuredEnd"] ?? NULL;

      if ($start && $end) {
        $start_fmt = $this->formatDate(
        $start,
        $crime["timeOffenseOccuredStart"] ?? NULL
        );
        $end_fmt = $this->formatDate(
          $end,
          $crime["timeOffenseOccuredEnd"] ?? NULL
        );
        $crime["dateOffenseOccured"] =
          $start_fmt !== $end_fmt
            ? "Between {$start_fmt} and {$end_fmt}"
            : $start_fmt;
      }
      elseif ($start) {
        $crime["dateOffenseOccured"] = $this->formatDate(
        $start,
        $crime["timeOffenseOccured"] ?? NULL
        );
      }

      // Format reported date.
      if (!empty($crime["dateOffenseReported"])) {
        $crime["dateOffenseReported"] = $this->formatDate(
        $crime["dateOffenseReported"],
        $crime["timeOffenseReported"] ?? NULL
        );
      }
    }
    return $crimes;
  }

}
