<?php

namespace Drupal\safety_core\Form;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\safety_core\Controller\CleryController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Form for log search functionality with AJAX updates.
 */
class LogSearchForm extends FormBase {

  /**
   * The Clery controller service.
   *
   * @var \Drupal\safety_core\Controller\CleryController
   */
  protected $cleryController;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The log type (crime, fire, etc.).
   *
   * @var string
   */
  protected $logType;

  /**
   * The log block instance.
   *
   * @var \Drupal\safety_core\Plugin\Block\LogBlock
   */
  protected $logBlock;

  /**
   * Constructs a new LogSearchForm instance.
   */
  public function __construct(
    CleryController $clery_controller,
    RequestStack $request_stack,
    LoggerInterface $logger,
    RendererInterface $renderer,
  ) {
    $this->cleryController = $clery_controller;
    $this->requestStack = $request_stack;
    $this->logger = $logger;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LogSearchForm {
    return new static(
      $container->get('safety_core.clery_controller'),
      $container->get('request_stack'),
      $container->get('logger.channel.safety_core'),
      $container->get('renderer')
    );
  }

  /**
   * Sets the log type and block instance.
   *
   * @param string $log_type
   *   The log type.
   * @param \Drupal\safety_core\Plugin\Block\LogBlock $log_block
   *   The log block instance.
   */
  /**
   * Sets the log type and block instance.
   *
   * @param string $log_type
   *   The log type.
   * @param \Drupal\safety_core\Plugin\Block\LogBlock $log_block
   *   The log block instance.
   */
  public function setLogContext($log_type, $log_block): void {
    $this->logType = $log_type;
    $this->logBlock = $log_block;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'log_search_form_' . ($this->logType ?? 'generic');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->requestStack->getCurrentRequest();
    $start_date = $request->query->get('start_date');
    $end_date = $request->query->get('end_date');

    // Set defaults if no search performed.
    if (!$start_date && !$end_date) {
      $defaults = $this->logBlock ? $this->logBlock->getDefaultDateRange() : $this->getDefaultDateRange();
      $start_date = $defaults['start'];
      $end_date = $defaults['end'];
    }

    $min_date = (new DrupalDateTime())->modify('-360 days')->format('Y-m-d');
    $max_date = (new DrupalDateTime())->format('Y-m-d');

    $form_wrapper_id = 'log-search-form-wrapper-' . $this->logType;

    $form['#prefix'] = '<div id="' . $form_wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $form['#attributes'] = [
      'class' => [
        'bg--gray',
        'uids-content',
        'element--padding__all--minimal',
        'block-margin__bottom--extra',
        'uids-content--horizontal',
      ],
    ];

    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      '#default_value' => $start_date,
      '#attributes' => ['min' => $min_date, 'max' => $max_date],
      '#wrapper_attributes' => ['class' => ['uids-content--horizontal-flex']],
    ];

    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#default_value' => $end_date,
      '#attributes' => ['min' => $min_date, 'max' => $max_date],
      '#wrapper_attributes' => ['class' => ['uids-content--horizontal-flex']],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => ['form-actions'],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['bg--black'],
      ],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'effect' => 'fade',
      ],
    ];

    $form['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('<current>'),
      '#attributes' => [
        'class' => ['button', 'bttn--secondary'],
        'role' => 'button',
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback for form updates.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Get form values and build results.
    $start_date = $form_state->getValue('start_date');
    $end_date = $form_state->getValue('end_date');

    if (!$start_date && !$end_date) {
      $defaults = $this->logBlock ? $this->logBlock->getDefaultDateRange() : $this->getDefaultDateRange();
      $start_date = $defaults['start'];
      $end_date = $defaults['end'];
    }

    $results = $this->buildResults($start_date, $end_date);

    // Update results in external container.
    $results_wrapper_id = '#log-search-results-wrapper-' . $this->logType;
    $response->addCommand(new HtmlCommand($results_wrapper_id, $results));

    // Announce the results.
    $message = $this->buildAnnounceMessage($start_date, $end_date, $results);
    $response->addCommand(new AnnounceCommand($message, 'polite'));

    return $response;
  }

  /**
   * Builds the results section.
   *
   * @param string $start_date
   *   The start date.
   * @param string $end_date
   *   The end date.
   *
   * @return array
   *   The results render array.
   */
  protected function buildResults($start_date, $end_date): array {
    $build = [];

    if (!$this->logBlock) {
      return $build;
    }

    try {
      // Always show all results.
      $limit = NULL;

      // Get log data using the LogBlock instance.
      $log_data = $this->logBlock->getLogData($start_date, $end_date, $limit);
      $log_count = count($log_data);

      $build = [
        '#theme' => 'log_table',
        '#' . $this->logBlock->getDataKey() => $log_data,
        '#log_type' => $this->logType,
        '#start_date' => (new DrupalDateTime($start_date))->format('m/d/Y'),
        '#end_date' => (new DrupalDateTime($end_date))->format('m/d/Y'),
        '#cache' => ['max-age' => 3600],
      ];

      // Store count for announce message.
      $build['#log_count'] = $log_count;

    }
    catch (\Exception $e) {
      $this->logger->error('@type log API error: @message', [
        '@type' => ucfirst($this->logType),
        '@message' => $e->getMessage(),
      ]);

      $build = [
        '#markup' => $this->t('Failed to load @type logs. Please try again later.', [
          '@type' => $this->logType,
        ]),
      ];

      // Store error for announce message.
      $build['#error'] = TRUE;
    }

    return $build;
  }

  /**
   * Builds the announce message for AJAX updates.
   *
   * @param string $start_date
   *   The start date.
   * @param string $end_date
   *   The end date.
   * @param array $build
   *   The build array with results.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The announce message.
   */
  protected function buildAnnounceMessage($start_date, $end_date, array $build): TranslatableMarkup {
    if (isset($build['#error'])) {
      return $this->t('Error loading @type log data. Please try again later.', [
        '@type' => $this->logType,
      ]);
    }

    $count = $build['#log_count'] ?? 0;
    $formatted_start = (new DrupalDateTime($start_date))->format('m/d/Y');
    $formatted_end = (new DrupalDateTime($end_date))->format('m/d/Y');

    if ($count > 0) {
      return $this->formatPlural(
        $count,
        'Found 1 @type log entry from @start to @end.',
        'Found @count @type log entries from @start to @end.',
        [
          '@type' => $this->logType,
          '@start' => $formatted_start,
          '@end' => $formatted_end,
        ]
      );
    }
    else {
      return $this->t('No @type log entries found from @start to @end.', [
        '@type' => $this->logType,
        '@start' => $formatted_start,
        '@end' => $formatted_end,
      ]);
    }
  }

  /**
   * Gets the default date range.
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
   * Gets initial results for external rendering.
   *
   * This method allows the calling code (like LogBlock) to get the initial
   * results to render outside of the form.
   *
   * @param string|null $start_date
   *   The start date. If null, uses default.
   * @param string|null $end_date
   *   The end date. If null, uses default.
   *
   * @return array
   *   The results render array.
   */
  public function getInitialResults($start_date = NULL, $end_date = NULL): array {
    $request = $this->requestStack->getCurrentRequest();

    // Use provided dates or get from request.
    if (!$start_date) {
      $start_date = $request->query->get('start_date');
    }
    if (!$end_date) {
      $end_date = $request->query->get('end_date');
    }

    // Set defaults if no dates available.
    if (!$start_date && !$end_date) {
      $defaults = $this->logBlock ? $this->logBlock->getDefaultDateRange() : $this->getDefaultDateRange();
      $start_date = $defaults['start'];
      $end_date = $defaults['end'];
    }

    return $this->buildResults($start_date, $end_date);
  }

  /**
   * Gets the results wrapper ID for external use.
   *
   * @return string
   *   The results wrapper ID.
   */
  public function getResultsWrapperId(): string {
    return 'log-search-results-wrapper-' . $this->logType;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Form submission is handled by AJAX.
  }

}
