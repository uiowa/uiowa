<?php

namespace Drupal\safety_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides a Crime Log block.
 *
 * @Block(
 *   id = "crime_log_block",
 *   admin_label = @Translation("Crime log"),
 *   category = @Translation("Site custom")
 * )
 */
class CrimeLogBlock extends BlockBase implements ContainerFactoryPluginInterface {
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

  /**
   * The cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The base URL for the Clery Edge API.
   */
  const BASE_URL = 'https://app-cleryedge-api-prod.azurewebsites.net/api/public';

  /**
   * Constructs a new CrimeLogBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    CacheBackendInterface $cache_backend,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * Creates an instance of the plugin.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('cache.default')
    );
  }

  /**
   * Gets the API key from configuration.
   */
  protected function getApiKey() {
    return $this->configFactory
      ->get('safety_core.settings')
      ->get('clery_api.api_key') ?: '';
  }

  /**
   * Gets cached crime data or fetches from API using bucket system.
   */
  protected function getCrimeData($start_date, $end_date, $limit = NULL) {
    // Create cache bucket.
    $bucket = (new \DateTime($end_date))->format('Y-m');
    $cid = 'safety_core:crime_log:bucket:' . $bucket;

    // Check cache first.
    if ($cache = $this->cacheBackend->get($cid)) {
      $cached_data = $cache->data;
    }
    else {
      // Fetch date range for bucket.
      $bucket_start = (new \DateTime($bucket . '-01'))->modify('-60 days')->format('Y-m-d');
      $bucket_end = (new \DateTime($bucket . '-01'))->modify('last day of this month')->format('Y-m-d');

      $cached_data = $this->fetchCrimeData($bucket_start, $bucket_end);

      // Cache for 24 hours.
      $this->cacheBackend->set($cid, $cached_data, time() + 86400);
    }

    // Filter cached data to requested range.
    $filtered_data = $this->filterByDateRange($cached_data, $start_date, $end_date);

    return $limit ? array_slice($filtered_data, 0, $limit) : $filtered_data;
  }

  /**
   * Filters crime data by date range.
   *
   * @throws \Exception
   */
  protected function filterByDateRange($data, $start_date, $end_date) {
    $start_timestamp = strtotime($start_date);
    $end_timestamp = strtotime($end_date . ' 23:59:59');

    return array_filter($data, function ($crime) use ($start_timestamp, $end_timestamp) {
      $crime_date = $crime['dateOffenseReported'] ?? '';
      if (empty($crime_date)) {
        return FALSE;
      }

      // Check if there's a time.
      if (preg_match('/\d{1,2}\/\d{1,2}\/\d{4} \d{1,2}:\d{2}/', $crime_date)) {
        $crime_timestamp = \DateTime::createFromFormat('m/d/Y H:i', $crime_date);
      }
      elseif (preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $crime_date)) {
        $crime_timestamp = \DateTime::createFromFormat('m/d/Y', $crime_date);
      }
      else {
        $crime_timestamp = new \DateTime($crime_date);
      }

      $crime_timestamp = $crime_timestamp ? $crime_timestamp->getTimestamp() : 0;

      return $crime_timestamp >= $start_timestamp && $crime_timestamp <= $end_timestamp;
    });
  }

  /**
   * Fetches crime data from the API.
   */
  protected function fetchCrimeData($start_date, $end_date) {
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
      'query' => [
        'FromDateReported' => $start_date,
        'ToDateReported' => $end_date,
      ],
      'timeout' => 10,
    ]);

    if ($response->getStatusCode() !== 200) {
      throw new \Exception('API request failed with status: ' . $response->getStatusCode());
    }

    $data = json_decode($response->getBody()->getContents(), TRUE);
    if (!is_array($data)) {
      throw new \Exception('Invalid API response format');
    }

    // Sort by newest first.
    usort($data, function ($a, $b) {
      $datetime_a = ($a['dateOffenseReported'] ?? '') . ' ' . ($a['timeOffenseReported'] ?? '00:00:00');
      $datetime_b = ($b['dateOffenseReported'] ?? '') . ' ' . ($b['timeOffenseReported'] ?? '00:00:00');

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
      $start = $crime['dateOffenseOccuredStart'] ?? ($crime['dateOffenseOccured'] ?? NULL);
      $end = $crime['dateOffenseOccuredEnd'] ?? NULL;

      if ($start && $end) {
        $start_fmt = $this->formatDate($start, $crime['timeOffenseOccuredStart'] ?? NULL);
        $end_fmt = $this->formatDate($end, $crime['timeOffenseOccuredEnd'] ?? NULL);
        $crime['dateOffenseOccured'] = $start_fmt !== $end_fmt
          ? "Between {$start_fmt} and {$end_fmt}"
          : $start_fmt;
      }
      elseif ($start) {
        $crime['dateOffenseOccured'] = $this->formatDate($start, $crime['timeOffenseOccured'] ?? NULL);
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
   * Formats a date string with optional time.
   */
  protected function formatDate($date_string, $time_string = NULL) {
    if (empty($date_string)) {
      return NULL;
    }

    $combined = trim($date_string) . ($time_string ? ' ' . trim($time_string) : '');
    $datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $combined) ?:
      \DateTime::createFromFormat('Y-m-d', $date_string);

    return $datetime ? $datetime->format($time_string ? 'm/d/Y H:i' : 'm/d/Y') : NULL;
  }

  /**
   * Builds the render array for this block.
   */
  public function build() {
    $build = [];
    $request = \Drupal::request();
    $start_date = $request->query->get('start_date');
    $end_date = $request->query->get('end_date');

    // Set defaults if no search performed.
    if (!$start_date && !$end_date) {
      $start_date = (new DrupalDateTime('yesterday'))->format('Y-m-d');
      $end_date = (new DrupalDateTime('today'))->format('Y-m-d');
      // Show only 5 recent for initial load.
      $limit = 5;
    }
    else {
      // Show all results for search.
      $limit = NULL;
    }

    // Build search form.
    $min_date = (new DrupalDateTime('-60 days'))->format('Y-m-d');
    $max_date = (new DrupalDateTime())->format('Y-m-d');

    $build['#attached']['library'][] = 'safety_core/clery';

    $build['search_form'] = [
      '#type' => 'form',
      '#method' => 'get',
      '#attributes' => [
        'class' => [
          'bg--gray',
          'uids-content',
          'element--padding__all--minimal',
          'block-margin__bottom--extra',
          'form-horizontal',
        ],
      ],
      'start_date' => [
        '#type' => 'date',
        '#title' => $this->t('Start Date'),
        '#name' => 'start_date',
        '#value' => $request->query->get('start_date'),
        '#attributes' => ['min' => $min_date, 'max' => $max_date],
        '#wrapper_attributes' => ['class' => ['form-horizontal-flex']],
      ],
      'end_date' => [
        '#type' => 'date',
        '#title' => $this->t('End Date'),
        '#name' => 'end_date',
        '#value' => $request->query->get('end_date'),
        '#attributes' => ['min' => $min_date, 'max' => $max_date],
        '#wrapper_attributes' => ['class' => ['form-horizontal-flex']],
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Search'),
          '#button_type' => 'primary',
          '#attributes' => ['class' => ['bg--black']],
        ],
      ],
    ];

    try {
      $crimes = $this->getCrimeData($start_date, $end_date, $limit);

      $build['results'] = [
        '#theme' => 'crime_log_table',
        '#crimes' => $crimes,
        '#start_date' => (new DrupalDateTime($start_date))->format('m/d/Y'),
        '#end_date' => (new DrupalDateTime($end_date))->format('m/d/Y'),
        '#cache' => ['max-age' => 3600],
      ];
    }
    catch (\Exception $e) {
      \Drupal::logger('safety_core')->error('Crime log API error: @message', ['@message' => $e->getMessage()]);
      $build['error'] = [
        '#markup' => $this->t('Failed to load crime logs. Please try again later.'),
      ];
    }

    return $build;
  }

  /**
   * Gets cache contexts for this block.
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.query_args']);
  }

  /**
   * Gets cache tags for this block.
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['crime_log_data']);
  }

  /**
   * Gets the cache max age for this block.
   */
  public function getCacheMaxAge() {
    return 3600;
  }

}
