<?php

namespace Drupal\safety_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\safety_core\Controller\CrimeLogController;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

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
   * {@inheritdoc}
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
   * Get crime log controller instance.
   *
   * @return \Drupal\safety_core\Controller\CrimeLogController
   *   The crime log controller instance.
   */
  protected function getCrimeLogController() {
    return new CrimeLogController($this->httpClient, $this->configFactory);
  }

  /**
   * {@inheritdoc}
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
      $params = [
        'FromDateReported' => $start_date,
        'ToDateReported' => $end_date,
      ];

      $crimeLogController = $this->getCrimeLogController();
      $crimes = $crimeLogController->fetchCrimeData($params, $limit);

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

}
