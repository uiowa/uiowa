<?php

namespace Drupal\registrar_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\registrar_core\Controller\AcademicCalendarController;
use Drupal\registrar_core\SessionColorTrait;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides an 'Five Year Academic Calendar' block.
 *
 * @Block(
 *   id = "five_year_academic_calendar_block",
 *   admin_label = @Translation("Five Year Academic Calendar"),
 *   category = @Translation("Site custom")
 * )
 */
class FiveYearAcademicCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface, FormInterface, TrustedCallbackInterface {
  use SessionColorTrait;

  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new AcademicCalendarBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Drupal\registrar_core\Controller\AcademicCalendarController $academicCalendarController
   *   The Academic Calendar Controller.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui, FormBuilderInterface $formBuilder, RequestStack $requestStack, AcademicCalendarController $academicCalendarController) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
    $this->formBuilder = $formBuilder;
    $this->requestStack = $requestStack;
    $this->academicCalendarController = $academicCalendarController;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uiowa_maui.api'),
      $container->get('form_builder'),
      $container->get('request_stack'),
      $container->get('registrar_core.academic_calendar_controller')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'five_year_academic_calendar_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Add any validation if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle form submission if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = $this->formBuilder->getForm($this);

    $build = [
      'wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'list-container__inner',
            'sitenow-academic-calendar',
          ],
        ],
        'form' => $form,
        'calendar' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['academic-calendar content'],
            'id' => 'academic-calendar-content',
          ],
          'content' => [
            '#lazy_builder' => [
              static::class . '::lazyBuilder',
              [],
            ],
            '#create_placeholder' => TRUE,
          ],
        ],
      ],
    ];

    // Attach the library for the calendar.
    $build['#attached']['library'][] = 'uids_base/card';
    $build['#attached']['library'][] = 'registrar_core/five-year-academic-calendar';

    return $build;
  }

  /**
   * Builds the form elements for the block.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['#id'] = 'academic-calendar-filter-form';
    $form['#attributes']['class'][] = 'academic-calendar-filters sidebar element--padding__all--minimal bg--gray';

    $yearOptions = $this->maui->getYearOptions(4, 4);
    $defaultYear = $this->academicCalendarController->getFallSession()->id;

    $form['start_year'] = [
      '#type' => 'select',
      '#title' => $this->t('Academic Year'),
      '#default_value' => $defaultYear,
      '#options' => $yearOptions,
      '#attributes' => ['class' => ['academic-calendar-year']],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['form-actions--stacked']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => ['class' => ['bttn--full']],
    ];

    $form['actions']['reset'] = [
      '#type' => 'button',
      '#value' => $this->t('Reset'),
      '#attributes' => [
        'class' => [
          'bttn',
          'bttn--secondary',
          'bttn--full',
          'js-form-reset',
        ],
      ],
    ];

    // Add configuration values to drupalSettings.
    $form['#attached']['drupalSettings']['academicCalendar'] = [
      'yearOptions' => $yearOptions,
      'defaultYear' => $defaultYear,
    ];

    return $form;
  }

  /**
   * A #lazy_builder callback.
   */
  public static function lazyBuilder() {
    $block_manager = \Drupal::service('plugin.manager.block');
    $skeletonLoader = $block_manager->createInstance('skeleton_load_block')->build();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['academic-calendar', 'content']],
      'content' => [
        $skeletonLoader,
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks() {
    return ['lazyBuilder'];
  }

}
