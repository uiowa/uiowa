<?php

namespace Drupal\safety_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for CleryEdge crime log integration.
 */
class CrimeLogController extends ControllerBase {

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  const BASE_URL = 'https://app-cleryedge-api-prod.azurewebsites.net/api/public';

  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /**
   * Get the API key from configuration.
   *
   * @return string
   *   The API key or empty string if not configured.
   */
  public function getApiKey() {
    return $this->configFactory
      ->get('safety_core.settings')
      ->get('clery_api.api_key') ?:
      '';
  }

  /**
   * Format date and time strings into display format.
   *
   * @param string $date_string
   *   The date string in Y-m-d format.
   * @param string|null $time_string
   *   Optional time string in H:i:s format.
   *
   * @return string|null
   *   Formatted date string or NULL if invalid.
   */
  public function formatDate($date_string, $time_string = NULL) {
    if (empty($date_string)) {
      return NULL;
    }

    $combined =
      trim($date_string) . ($time_string ? ' ' . trim($time_string) : '');
    $datetime =
      \DateTime::createFromFormat('Y-m-d H:i:s', $combined) ?:
        \DateTime::createFromFormat('Y-m-d', $date_string);

    return $datetime
      ? $datetime->format($time_string ? 'm/d/Y H:i' : 'm/d/Y')
      : NULL;
  }

  /**
   * Format crime date fields for display.
   *
   * @param array $crimes
   *   Array of crime data.
   *
   * @return array
   *   Array of crimes with formatted date fields.
   */
  public function formatCrimeDates(array $crimes) {
    foreach ($crimes as &$crime) {
      // Format occurred date.
      $start =
        $crime['dateOffenseOccuredStart'] ??
        ($crime['dateOffenseOccured'] ?? NULL);
      $end = $crime['dateOffenseOccuredEnd'] ?? NULL;

      if ($start && $end) {
        $start_fmt = $this->formatDate(
          $start,
          $crime['timeOffenseOccuredStart'] ?? NULL
        );
        $end_fmt = $this->formatDate(
          $end,
          $crime['timeOffenseOccuredEnd'] ?? NULL
        );

        $crime['dateOffenseOccured'] =
          $start_fmt !== $end_fmt
            ? "Between {$start_fmt} and {$end_fmt}"
            : $start_fmt;
      }
      elseif ($start) {
        $crime['dateOffenseOccured'] = $this->formatDate(
          $start,
          $crime['timeOffenseOccured'] ?? NULL
        );
      }

      // Format reported date.
      if (!empty($crime['dateOffenseReported'])) {
        $crime['dateOffenseReported'] = $this->formatDate(
          $crime['dateOffenseReported'],
          $crime['timeOffenseReported'] ?? NULL
        );
      }
    }
    return $crimes;
  }

  /**
   * Fetch crime data from the CleryEdge API.
   *
   * @param array $params
   *   Query parameters for the API request.
   * @param int|null $limit
   *   Optional limit on number of results.
   *
   * @return array
   *   Array of crime data with formatted dates.
   *
   * @throws \Exception
   *   If API key is missing or API request fails.
   */
  public function fetchCrimeData(array $params, $limit = NULL) {
    $api_key = $this->getApiKey();
    if (empty($api_key)) {
      throw new \Exception('API key not configured');
    }

    $response = $this->httpClient->get(self::BASE_URL . '/report/crime-log', [
      'headers' => [
        'x-api-key' => $api_key,
        'Accept' => 'application/json',
      ],
      'verify' => FALSE,
      'query' => $params,
      'timeout' => 30,
    ]);

    if ($response->getStatusCode() !== 200) {
      throw new \Exception('API request failed');
    }

    $data = json_decode($response->getBody()->getContents(), TRUE);
    if (!is_array($data)) {
      throw new \Exception('Invalid API response');
    }

    // Sort by reported date (newest first).
    usort($data, function ($a, $b) {
      return strtotime($b['dateOffenseReported'] ?? 0) -
        strtotime($a['dateOffenseReported'] ?? 0);
    });

    if ($limit) {
      $data = array_slice($data, 0, $limit);
    }

    return $this->formatCrimeDates($data);
  }

  /**
   * Get crime logs based on request parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return array
   *   Render array for crime log table.
   */
  public function getCrimeLogs(Request $request) {
    try {
      $params = [];

      // Get date parameters.
      $from_reported = $request->query->get('from_date_reported');
      $to_reported = $request->query->get('to_date_reported');
      $from_occurred = $request->query->get('from_date_occurred');
      $to_occurred = $request->query->get('to_date_occurred');

      // Set defaults.
      if (!$from_reported && !$from_occurred) {
        $from_reported = (new DrupalDateTime('-30 days'))->format('Y-m-d');
      }
      if (!$to_reported && !$to_occurred) {
        $to_reported = (new DrupalDateTime())->format('Y-m-d');
      }

      // Build query.
      if ($from_reported) {
        $params['FromDateReported'] = $from_reported;
      }
      if ($to_reported) {
        $params['ToDateReported'] = $to_reported;
      }
      if ($from_occurred) {
        $params['FromDateOccured'] = $from_occurred;
      }
      if ($to_occurred) {
        $params['ToDateOccured'] = $to_occurred;
      }

      $data = $this->fetchCrimeData($params);

      return [
        '#theme' => 'crime_log_table',
        '#crimes' => $data,
        '#cache' => ['max-age' => 3600, 'contexts' => ['url.query_args']],
      ];
    }
    catch (\Exception $e) {
      return [
        '#markup' => $this->t(
          'Failed to fetch crime logs. Please try again later.'
        ),
      ];
    }
  }

  /**
   * Get recent crime logs.
   *
   * @param int $amount
   *   Number of recent logs to retrieve (default: 5).
   *
   * @return array
   *   Render array for crime log table.
   */
  public function getRecentLogs($amount = 5) {
    try {
      $params = [
        'FromDateReported' => (new DrupalDateTime('-30 days'))->format('Y-m-d'),
        'ToDateReported' => (new DrupalDateTime())->format('Y-m-d'),
      ];

      $data = $this->fetchCrimeData($params, $amount);

      return [
        '#theme' => 'crime_log_table',
        '#crimes' => $data,
        '#cache' => ['max-age' => 3600],
      ];
    }
    catch (\Exception $e) {
      return [
        '#markup' => $this->t(
          'Failed to fetch recent crime logs. Please try again later.'
        ),
      ];
    }
  }

}
