<?php

namespace Drupal\safety_core\Controller;

use Drupal\Core\Datetime\DrupalDateTime;
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
   * Gets cached crime data or fetches from API.
   */
  public function getCrimeData($start_date, $end_date, $limit = NULL) {
    $cid = "safety_core:crime_log:" . $start_date . ":" . $end_date;
    if ($limit) {
      $cid .= ":" . $limit;
    }

    if ($cache = $this->cacheBackend->get($cid)) {
      $data = $cache->data;
    }
    else {
      $data = $this->fetchCrimeData($start_date, $end_date);
      // Cache for 24 hours.
      $this->cacheBackend->set($cid, $data, time() + 86400);
    }

    return $limit ? array_slice($data, 0, $limit) : $data;
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

  /**
   * Formats a date string with optional time.
   */
  protected function formatDate($date_string, $time_string = NULL): ?string {
    if (empty($date_string)) {
      return NULL;
    }

    $combined =
      trim($date_string) . ($time_string ? " " . trim($time_string) : "");
    $datetime =
      \DateTime::createFromFormat("Y-m-d H:i:s", $combined) ?:
      \DateTime::createFromFormat("Y-m-d", $date_string);

    return $datetime
      ? $datetime->format($time_string ? "m/d/Y H:i" : "m/d/Y")
      : NULL;
  }

  /**
   * Submits a new incident report to the API.
   */
  public function submitIncidentReport(array $incident_data) {
    return $this->apiPost('/incident', $incident_data);
  }

  /**
   * Formats time string to proper format for API (HH:MM:SS).
   */
  public function formatTime($time_string): ?string {
    if (empty($time_string)) {
      return NULL;
    }

    // Handle DrupalDateTime objects from datetime form elements.
    if ($time_string instanceof DrupalDateTime) {
      return $time_string->format('H:i:s');
    }

    // If already in HH:MM:SS format, return as is.
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time_string)) {
      return $time_string;
    }

    // If already in HH:MM format, add seconds.
    if (preg_match('/^\d{2}:\d{2}$/', $time_string)) {
      return $time_string . ':00';
    }

    // If in H:MM format, pad with leading zero and add seconds.
    if (preg_match('/^\d{1}:\d{2}$/', $time_string)) {
      return str_pad($time_string, 5, '0', STR_PAD_LEFT) . ':00';
    }

    // Try to parse and format various time formats.
    $time = \DateTime::createFromFormat('H:i', $time_string);
    if ($time === FALSE) {
      $time = \DateTime::createFromFormat('g:i A', $time_string);
    }
    if ($time === FALSE) {
      $time = \DateTime::createFromFormat('g:i a', $time_string);
    }

    return $time ? $time->format('H:i:s') : NULL;
  }

  /**
   * Builds the request body for API submission from form values.
   */
  public function buildIncidentRequestData(array $form_values) {
    $body = [];

    // Incident Detail.
    $body['incidentDetail'] = [
      'dateOffenseReported' => $form_values['date_offense_reported'] ?: NULL,
      'timeOffenseReported' => $form_values['time_offense_reported'] ? $this->formatTime($form_values['time_offense_reported']) : NULL,
      'dateOffenseOccured' => $form_values['date_offense_occured'] ?: NULL,
      'exactTimeOccured' => $form_values['exact_time_occured'] ? $this->formatTime($form_values['exact_time_occured']) : NULL,
      'dateStart' => $form_values['date_start'] ?: NULL,
      'timeStart' => $form_values['time_start'] ? $this->formatTime($form_values['time_start']) : NULL,
      'dateEnd' => $form_values['date_end'] ?: NULL,
      'timeEnd' => $form_values['time_end'] ? $this->formatTime($form_values['time_end']) : NULL,
      'specificLocation' => !empty($form_values['specific_location']) ? $form_values['specific_location'] : NULL,
      'description' => !empty($form_values['description']) ? $form_values['description'] : NULL,
    ];

    // Reporter.
    if (
      !empty($form_values['reporter_first_name']) &&
      !empty($form_values['reporter_last_name'])
    ) {
      $body['reporter'] = [
        'firstName' => $form_values['reporter_first_name'],
        'lastName' => $form_values['reporter_last_name'],
        'email' => !empty($form_values['reporter_email']) ? $form_values['reporter_email'] : NULL,
        'phone' => !empty($form_values['reporter_phone']) ? $form_values['reporter_phone'] : NULL,
      ];
    }
    else {
      $body['reporter'] = NULL;
    }

    // CSA Flag.
    $body['isReporterCsa'] = (bool) $form_values['is_reporter_csa'];

    // Geography ID hardcoded to an empty location.
    $body['geographyId'] = 1400;

    // Incident Contacts.
    $body['incidentContacts'] = [];
    if (
      isset($form_values['contacts_container']) &&
      is_array($form_values['contacts_container'])
    ) {
      foreach ($form_values['contacts_container'] as $contact_data) {
        if (
          !empty($contact_data['first_name']) &&
          !empty($contact_data['last_name'])
        ) {
          $contact = [
            'firstName' => $contact_data['first_name'],
            'lastName' => $contact_data['last_name'],
            'email' => !empty($contact_data['email']) ? $contact_data['email'] : NULL,
            'phone' => !empty($contact_data['phone']) ? $contact_data['phone'] : NULL,
            'dateOfBirth' => !empty($contact_data['date_of_birth']) ? $contact_data['date_of_birth'] : NULL,
            'contactRoles' => [],
          ];

          $body['incidentContacts'][] = $contact;
        }
      }
    }

    return $body;
  }

}
