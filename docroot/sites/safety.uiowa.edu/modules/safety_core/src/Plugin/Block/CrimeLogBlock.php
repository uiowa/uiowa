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
   * The Clery controller service.
   *
   * @var \Drupal\safety_core\Controller\CleryController
   */
  protected $cleryController;

  /**
   * Constructs a new CrimeLogBlock instance.
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
   * Builds the render array for this block.
   */
  public function build() {
    $build = [];
    $request = \Drupal::request();
    $start_date = $request->query->get('start_date');
    $end_date = $request->query->get('end_date');

    // Set defaults if no search performed.
    if (!$start_date && !$end_date) {
      $start_date = (new DrupalDateTime('7 days ago'))->format('Y-m-d');
      $end_date = (new DrupalDateTime('today'))->format('Y-m-d');
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
      $crimes = $this->cleryController->getCrimeData($start_date, $end_date, $limit);
      $crime_count = count($crimes);

      $build['results'] = [
        '#theme' => 'crime_log_table',
        '#crimes' => $crimes,
        '#start_date' => (new DrupalDateTime($start_date))->format('m/d/Y'),
        '#end_date' => (new DrupalDateTime($end_date))->format('m/d/Y'),
        '#cache' => ['max-age' => 3600],
      ];

      // Pass data to JS for announce functionality.
      $build['#attached']['drupalSettings']['crimeLog'] = [
        'crimeCount' => $crime_count,
        'startDate' => (new DrupalDateTime($start_date))->format('m/d/Y'),
        'endDate' => (new DrupalDateTime($end_date))->format('m/d/Y'),
        'isSearch' => !empty($request->query->get('start_date')) || !empty($request->query->get('end_date')),
      ];

    }
    catch (\Exception $e) {
      \Drupal::logger('safety_core')->error('Crime log API error: @message', ['@message' => $e->getMessage()]);
      $build['error'] = [
        '#markup' => $this->t('Failed to load crime logs. Please try again later.'),
      ];

      // Pass errors to JS.
      $build['#attached']['drupalSettings']['crimeLog'] = [
        'error' => TRUE,
        'isSearch' => !empty($request->query->get('start_date')) || !empty($request->query->get('end_date')),
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
    $request = \Drupal::request();
    $start_date = $request->query->get('start_date');
    $end_date = $request->query->get('end_date');

    // Set defaults if no search performed.
    if (!$start_date && !$end_date) {
      $start_date = (new DrupalDateTime('7 days ago'))->format('Y-m-d');
      $end_date = (new DrupalDateTime('today'))->format('Y-m-d');
    }

    // Create date-specific cache tags.
    $tags = ['crime_log_data'];

    // Add monthly cache tags for better invalidation.
    if ($start_date && $end_date) {
      $start_month = (new DrupalDateTime($start_date))->format('Y-m');
      $end_month = (new DrupalDateTime($end_date))->format('Y-m');

      $tags[] = "crime_log_data:month:{$start_month}";
      if ($start_month !== $end_month) {
        $tags[] = "crime_log_data:month:{$end_month}";
      }
    }

    return Cache::mergeTags(parent::getCacheTags(), $tags);
  }

  /**
   * Gets the cache max age for this block.
   */
  public function getCacheMaxAge() {
    $request = \Drupal::request();
    $end_date = $request->query->get('end_date');

    if (!$end_date) {
      $end_date = (new DrupalDateTime('today'))->format('Y-m-d');
    }

    $today = (new DrupalDateTime())->format('Y-m-d');

    // Short cache for today's data, longer for historical.
    return ($end_date === $today) ? 900 : 3600;
  }

}
