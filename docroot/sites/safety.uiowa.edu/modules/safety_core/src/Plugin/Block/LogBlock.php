<?php

namespace Drupal\safety_core\Plugin\Block;

use Drupal\safety_core\Form\LogSearchForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\safety_core\Controller\CleryController;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;

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
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new LogBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CleryController $clery_controller,
    RequestStack $request_stack,
    LoggerInterface $logger,
    FormBuilderInterface $form_builder,
    RendererInterface $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cleryController = $clery_controller;
    $this->requestStack = $request_stack;
    $this->logger = $logger;
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  /**
   * Creates an instance of the plugin.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): LogBlock {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('safety_core.clery_controller'),
      $container->get('request_stack'),
      $container->get('logger.channel.safety_core'),
      $container->get('form_builder'),
      $container->get('renderer'),
    );
  }

  /**
   * Gets the log type for this block.
   *
   * @return string
   *   The log type (crime, fire, etc.).
   */
  abstract public function getLogType();

  /**
   * Gets the data key for JS settings.
   *
   * @return string
   *   The data key.
   */
  abstract public function getDataKey();

  /**
   * Gets the count key for JS settings.
   *
   * @return string
   *   The count key.
   */
  abstract public function getCountKey();

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
  abstract public function getLogData($start_date, $end_date, $limit = NULL);

  /**
   * Gets the error message for failed API calls.
   *
   * @return string
   *   The error message.
   */
  protected function getErrorMessage(): TranslatableMarkup {
    return $this->t('Failed to load @type logs. Please try again later.', [
      '@type' => $this->getLogType(),
    ]);
  }

  /**
   * Builds the render array for this block.
   */
  public function build(): array {
    $build = [];

    $form_object = new LogSearchForm(
      $this->cleryController,
      $this->requestStack,
      $this->logger,
      $this->renderer
    );
    $form_object->setLogContext($this->getLogType(), $this);

    $build['search_form'] = $this->formBuilder->getForm($form_object);

    // Add results container that will be updated by AJAX.
    $build['results_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $form_object->getResultsWrapperId(),
      ],
    ];

    // Add initial results.
    $build['results_container']['results'] = $form_object->getInitialResults();

    return $build;
  }

  /**
   * Gets the default date range for this log type.
   *
   * @return array
   *   Array with 'start' and 'end' keys containing formatted dates.
   */
  public function getDefaultDateRange(): array {
    return [
      'start' => (new DrupalDateTime('7 days ago'))->format('Y-m-d'),
      'end' => (new DrupalDateTime('today'))->format('Y-m-d'),
    ];
  }

}
