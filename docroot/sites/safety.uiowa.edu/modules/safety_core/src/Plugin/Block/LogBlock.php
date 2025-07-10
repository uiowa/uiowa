<?php

namespace Drupal\safety_core\Plugin\Block;

use Drupal\Core\Url;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\safety_core\Controller\CleryController;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Base class for log blocks.
 */
abstract class LogBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Clery controller service.
   *
   * @var \Drupal\safety_core\Controller\CleryController
   */
  protected $cleryController;

  /**
   * Constructs a new LogBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CleryController $clery_controller,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cleryController = $clery_controller;
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
      $container->get('safety_core.clery_controller'),
    );
  }

  /**
   * Gets the log type for this block.
   *
   * @return string
   *   The log type (crime, fire, etc.).
   */
  abstract protected function getLogType();

  /**
   * Gets the data key for JS settings.
   *
   * @return string
   *   The data key.
   */
  abstract protected function getDataKey();

  /**
   * Gets the count key for JS settings.
   *
   * @return string
   *   The count key.
   */
  abstract protected function getCountKey();

  /**
   * Gets the cache tag for this block.
   *
   * @return string
   *   The cache tag.
   */
  abstract protected function getCacheTag();

  /**
   * Gets the log data from the controller.
   *
   * @param string $start_date
   *   The start date.
   * @param string $end_date
   *   The end date.
   * @param int|null $limit
   *   Optional limit.
   *
   * @return array
   *   The log data.
   */
  abstract protected function getLogData($start_date, $end_date, $limit = NULL);

  /**
   * Gets the logger channel name.
   *
   * @return string
   *   The logger channel.
   */
  protected function getLoggerChannel() {
    return 'safety_core';
  }

  /**
   * Gets the error message for failed API calls.
   *
   * @return string
   *   The error message.
   */
  protected function getErrorMessage() {
    return $this->t('Failed to load @type logs. Please try again later.', [
      '@type' => $this->getLogType(),
    ]);
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
      $defaults = $this->getDefaultDateRange();
      $start_date = $defaults['start'];
      $end_date = $defaults['end'];
    }

    // Always show all results.
    $limit = NULL;

    // Build search form.
    $min_date = (new DrupalDateTime())->modify('-60 days')->format('Y-m-d');
    $max_date = (new DrupalDateTime())->format('Y-m-d');

    $build['#attached']['library'][] = 'safety_core/clery-log';
    $build['#attached']['library'][] = 'core/drupal.announce';

    // Check if a search has been done.
    $has_search = !empty($request->query->get('start_date')) || !empty($request->query->get('end_date'));

    $actions = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Search'),
        '#button_type' => 'primary',
        '#attributes' => [
          'class' => ['bg--black'],
        ],
      ],
    ];

    // Only show reset button if a search was done.
    if ($has_search) {
      $actions['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => Url::fromRoute('<current>'),
        '#attributes' => [
          'class' => ['button', 'bttn--primary'],
          'role' => 'button',
        ],
      ];
    }

    $build['search_form'] = [
      '#type' => 'form',
      '#method' => 'get',
      '#attributes' => [
        'class' => [
          'bg--gray',
          'uids-content',
          'element--padding__all--minimal',
          'block-margin__bottom--extra',
          'uids-content--horizontal',
        ],
      ],
      'start_date' => [
        '#type' => 'date',
        '#title' => $this->t('Start Date'),
        '#name' => 'start_date',
        '#value' => $request->query->get('start_date'),
        '#attributes' => ['min' => $min_date, 'max' => $max_date],
        '#wrapper_attributes' => ['class' => ['uids-content--horizontal-flex']],
      ],
      'end_date' => [
        '#type' => 'date',
        '#title' => $this->t('End Date'),
        '#name' => 'end_date',
        '#value' => $request->query->get('end_date'),
        '#attributes' => ['min' => $min_date, 'max' => $max_date],
        '#wrapper_attributes' => ['class' => ['uids-content--horizontal-flex']],
      ],
      'actions' => [
        '#type' => 'actions',
        '#attributes' => [
          'class' => ['form-actions'],
        ],
      ] + $actions,
    ];

    try {
      $log_data = $this->getLogData($start_date, $end_date, $limit);
      $log_count = count($log_data);

      $build['results'] = [
        '#theme' => 'log_table',
        '#' . $this->getDataKey() => $log_data,
        '#log_type' => $this->getLogType(),
        '#start_date' => (new DrupalDateTime($start_date))->format('m/d/Y'),
        '#end_date' => (new DrupalDateTime($end_date))->format('m/d/Y'),
        '#cache' => ['max-age' => 3600],
      ];

      // Pass data to JS for announce functionality.
      $js_settings = [
        $this->getCountKey() => $log_count,
        'startDate' => (new DrupalDateTime($start_date))->format('m/d/Y'),
        'endDate' => (new DrupalDateTime($end_date))->format('m/d/Y'),
        'isSearch' => $has_search,
      ];

      $build['#attached']['drupalSettings'][$this->getJsSettingsKey()] = $js_settings;

    }
    catch (\Exception $e) {
      \Drupal::logger($this->getLoggerChannel())->error('@type log API error: @message', [
        '@type' => ucfirst($this->getLogType()),
        '@message' => $e->getMessage(),
      ]);

      $build['error'] = [
        '#markup' => $this->getErrorMessage(),
      ];

      // Pass errors to JS.
      $build['#attached']['drupalSettings'][$this->getJsSettingsKey()] = [
        'error' => TRUE,
        'isSearch' => $has_search,
      ];
    }

    return $build;
  }

  /**
   * Gets the default date range for this log type.
   *
   * @return array
   *   Array with 'start' and 'end' keys containing formatted dates.
   */
  protected function getDefaultDateRange() {
    return [
      'start' => (new DrupalDateTime('7 days ago'))->format('Y-m-d'),
      'end' => (new DrupalDateTime('today'))->format('Y-m-d'),
    ];
  }

  /**
   * Gets the JavaScript settings key for this block.
   *
   * @return string
   *   The JS settings key.
   */
  protected function getJsSettingsKey() {
    return $this->getLogType() . 'Log';
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
    return Cache::mergeTags(parent::getCacheTags(), [$this->getCacheTag()]);
  }

  /**
   * Gets the cache max age for this block.
   */
  public function getCacheMaxAge() {
    return 1800;
  }

}
