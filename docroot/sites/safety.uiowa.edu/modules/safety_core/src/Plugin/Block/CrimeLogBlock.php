<?php

namespace Drupal\safety_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides a Crime Log block.
 *
 * @Block(
 *   id = "crime_log_block",
 *   admin_label = @Translation("Crime Log"),
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
   * The base URL for the Clery Edge API.
   */
  const BASE_URL = 'https://app-cleryedge-api-prod.azurewebsites.net/api/public';

  /**
   * Constructs a new CrimeLogBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   Returns an instance of this plugin.
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
      $container->get('config.factory')
    );
  }

  /**
   * Gets the API key from configuration.
   *
   * @return string
   *   The API key or empty string if not configured.
   */
  protected function getApiKey() {
    return $this->configFactory
      ->get('safety_core.settings')
      ->get('clery_api.api_key') ?:
      '';
  }

  /**
   * Formats a date string with optional time.
   *
   * @param string $date_string
   *   The date string to format.
   * @param string|null $time_string
   *   Optional time string to append.
   *
   * @return string|null
   *   Formatted date string or NULL if invalid.
   */
  protected function formatDate($date_string, $time_string = NULL) {
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
   * Formats date fields in crime data array.
   *
   * @param array $crimes
   *   Array of crime data.
   *
   * @return array
   *   Crime data with formatted dates.
   */
  protected function formatCrimeDates(array $crimes) {
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
   * Fetches crime data from the API.
   *
   * @param string $start_date
   *   Start date for the query.
   * @param string $end_date
   *   End date for the query.
   * @param int|null $limit
   *   Optional limit for number of results.
   *
   * @return array
   *   Array of crime data.
   *
   * @throws \Exception
   *   When API request fails or returns invalid data.
   */
  protected function fetchCrimeData($start_date, $end_date, $limit = NULL) {
    $api_key = $this->getApiKey();
    if (empty($api_key)) {
      throw new \Exception('API key not configured');
    }

    $params = [
      'FromDateReported' => $start_date,
      'ToDateReported' => $end_date,
    ];

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

    // Sort by reported date (newest first)
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
   * Builds the render array for this block.
   *
   * @return array
   *   A render array.
   */
  public function build() {
    $build = [];

    // Get search parameters from URL.
    $request = \Drupal::request();
    $start_date = $request->query->get('start_date');
    $end_date = $request->query->get('end_date');

    // Set defaults if no search performed.
    if (!$start_date && !$end_date) {
      $start_date = (new DrupalDateTime('-30 days'))->format('Y-m-d');
      $end_date = (new DrupalDateTime())->format('Y-m-d');
      // Show only 5 recent for initial load.
      $limit = 5;
    }
    else {
      // Show all results for search.
      $limit = NULL;
    }

    // Build the search form directly.
    $min_date = (new DrupalDateTime('-60 days'))->format('Y-m-d');
    $max_date = (new DrupalDateTime())->format('Y-m-d');
    $default_start = (new DrupalDateTime('-30 days'))->format('Y-m-d');

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
        '#value' => $request->query->get('start_date') ?: $default_start,
        '#attributes' => ['min' => $min_date, 'max' => $max_date],
        '#wrapper_attributes' => ['class' => ['form-horizontal-flex']],
      ],
      'end_date' => [
        '#type' => 'date',
        '#title' => $this->t('End Date'),
        '#name' => 'end_date',
        '#value' => $request->query->get('end_date') ?: $max_date,
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
      $crimes = $this->fetchCrimeData($start_date, $end_date, $limit);

      // Render results table.
      $build['results'] = [
        '#theme' => 'crime_log_table',
        '#crimes' => $crimes,
        '#start_date' => (new DrupalDateTime($start_date))->format('m/d/Y'),
        '#end_date' => (new DrupalDateTime($end_date))->format('m/d/Y'),
        '#cache' => ['max-age' => 3600],
      ];
    }
    catch (\Exception $e) {
      $build['error'] = [
        '#markup' => $this->t(
          'Failed to load crime logs. Please try again later.'
        ),
      ];
    }

    return $build;
  }

  /**
   * Gets cache contexts for this block.
   *
   * @return array
   *   Array of cache contexts.
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.query_args']);
  }

  /**
   * Gets cache tags for this block.
   *
   * @return array
   *   Array of cache tags.
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['crime_log_data']);
  }

  /**
   * Gets the cache max age for this block.
   *
   * @return int
   *   Cache max age in seconds.
   */
  public function getCacheMaxAge() {
    return 3600;
  }

}
