<?php

namespace Drupal\safety_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
      'verify' => FALSE,
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

    // Decode and validate response.
    $data = json_decode($response->getBody()->getContents(), TRUE);
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

  /**
   * Formats a date string with optional time.
   */
  protected function formatDate($date_string, $time_string = NULL) {
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
   * Fetches states from the API.
   */
  public function getStates() {
    return $this->fetchFromApi("/incident/states");
  }

  /**
   * Fetches contact roles from the API.
   */
  public function getContactRoles() {
    return $this->fetchFromApi("/incident/contact/roles");
  }

  /**
   * Submits a new incident report to the API.
   */
  public function submitIncidentReport(array $incident_data) {
    return $this->apiPost('/incident', $incident_data);
  }

  /**
   * Generic method to fetch data from the API with caching.
   */
  protected function fetchFromApi($endpoint, $cache_duration = 3600) {
    // Create cache ID from endpoint.
    $cid = "safety_core:api_data:" . md5($endpoint);

    // Check cache first.
    if ($cache = $this->cacheBackend->get($cid)) {
      return $cache->data;
    }

    $data = $this->makeApiRequest($endpoint);

    if (!is_array($data)) {
      throw new \Exception("Expected array response for endpoint: " . $endpoint);
    }

    // Cache the data.
    $this->cacheBackend->set($cid, $data, time() + $cache_duration);

    return $data;
  }

  /**
   * Generic AJAX response wrapper.
   */
  protected function ajaxResponse($callback, ...$args) {
    try {
      $result = call_user_func_array([$this, $callback], $args);
      return new JsonResponse($result);
    }
    catch (\Exception $e) {
      return new JsonResponse(["error" => $e->getMessage()], 500);
    }
  }

  /**
   * AJAX endpoint to get all prerequisite data for the form.
   */
  public function ajaxGetPrerequisites() {
    try {
      $data = [
        "states" => $this->getStates(),
        "contactRoles" => $this->getContactRoles(),
      ];
      return new JsonResponse($data);
    }
    catch (\Exception $e) {
      return new JsonResponse(["error" => $e->getMessage()], 500);
    }
  }

  /**
   * AJAX endpoint to submit incident report.
   */
  public function ajaxSubmitIncident(Request $request) {
    try {
      $incident_data = json_decode($request->getContent(), TRUE);

      if (!$incident_data) {
        return new JsonResponse(["error" => "Invalid JSON data"], 400);
      }

      $result = $this->submitIncidentReport($incident_data);
      return new JsonResponse([
        "success" => TRUE,
        "message" => "Incident reported successfully",
        "data" => $result,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(["error" => $e->getMessage()], 500);
    }
  }

  /**
   * Formats time string to proper format for API.
   */
  public function formatTime($time_string): ?string {
    if (empty($time_string)) {
      return NULL;
    }

    // If already in HH:MM format, return as is.
    if (preg_match('/^\d{2}:\d{2}$/', $time_string)) {
      return $time_string;
    }

    // If in H:MM format, pad with leading zero.
    if (preg_match('/^\d{1}:\d{2}$/', $time_string)) {
      return str_pad($time_string, 5, '0', STR_PAD_LEFT);
    }

    // Try to parse and format various time formats.
    $time = \DateTime::createFromFormat('H:i', $time_string);
    if ($time === FALSE) {
      $time = \DateTime::createFromFormat('g:i A', $time_string);
    }
    if ($time === FALSE) {
      $time = \DateTime::createFromFormat('g:i a', $time_string);
    }

    return $time ? $time->format('H:i') : NULL;
  }

  /**
   * Builds form options from API data.
   */
  public function buildFormOptions($api_method, $id_key, $label_key, array $additional_params = []): array {
    try {
      $data = call_user_func_array([$this, $api_method], $additional_params);
      $options = [];

      if (is_array($data)) {
        foreach ($data as $item) {
          if (isset($item[$id_key], $item[$label_key])) {
            $options[$item[$id_key]] = $item[$label_key];
          }
        }
      }

      return $options;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Get contact role options formatted for form select.
   */
  public function getContactRoleOptions(): array {
    return $this->buildFormOptions('getContactRoles', 'contactRoleId', 'roleName');
  }

  /**
   * Validates incident form data.
   */
  public function validateIncidentData(array $form_values) {
    $errors = [];

    // Validate required fields.
    if (empty($form_values['date_offense_reported'])) {
      $errors[] = 'Date offense reported is required.';
    }

    if (empty($form_values['time_offense_reported'])) {
      $errors[] = 'Time offense reported is required.';
    }

    // Validate contacts if present.
    if (isset($form_values['contacts_container']) && is_array($form_values['contacts_container'])) {
      foreach ($form_values['contacts_container'] as $index => $contact_data) {
        if (!empty($contact_data['first_name']) || !empty($contact_data['last_name'])) {
          if (empty($contact_data['first_name'])) {
            $errors[] = "Contact " . ($index + 1) . ": First name is required.";
          }
          if (empty($contact_data['last_name'])) {
            $errors[] = "Contact " . ($index + 1) . ": Last name is required.";
          }
        }
      }
    }

    return $errors;
  }

  /**
   * Builds the request body for API submission from form values.
   */
  public function buildIncidentRequestData(array $form_values) {

    $body = [];

    // Incident Detail.
    $body['incidentDetail'] = [
      'dateOffenseReported' => $form_values['date_offense_reported'] ?? NULL,
      'timeOffenseReported' => $this->formatTime($form_values['time_offense_reported'] ?? NULL),
      'dateOffenseOccured' => $form_values['date_offense_occured'] ?? NULL,
      'exactTimeOccured' => $this->formatTime($form_values['exact_time_occured'] ?? NULL),
      'dateStart' => $form_values['date_start'] ?? NULL,
      'timeStart' => $this->formatTime($form_values['time_start'] ?? NULL),
      'dateEnd' => $form_values['date_end'] ?? NULL,
      'timeEnd' => $this->formatTime($form_values['time_end'] ?? NULL),
      'specificLocation' => $form_values['specific_location'] ?? NULL,
      'description' => $form_values['description'] ?? NULL,
    ];

    // Reporter.
    if (
      !empty($form_values['reporter_first_name']) &&
      !empty($form_values['reporter_last_name'])
    ) {
      $body['reporter'] = [
        'firstName' => $form_values['reporter_first_name'],
        'lastName' => $form_values['reporter_last_name'],
        'email' => $form_values['reporter_email'] ?? NULL,
        'phone' => $form_values['reporter_phone'] ?? NULL,
      ];
    }
    else {
      $body['reporter'] = NULL;
    }

    // CSA Flag.
    $body['isReporterCsa'] = (bool) $form_values['is_reporter_csa'];

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
            'email' => $contact_data['email'] ?? NULL,
            'phone' => $contact_data['phone'] ?? NULL,
            'dateOfBirth' => $contact_data['date_of_birth'] ?? NULL,
            'contactRoles' => array_map(
              'intval',
              array_filter($contact_data['contact_roles'] ?? [])
            ),
          ];

          $body['incidentContacts'][] = $contact;
        }
      }
    }

    return $body;
  }

}
