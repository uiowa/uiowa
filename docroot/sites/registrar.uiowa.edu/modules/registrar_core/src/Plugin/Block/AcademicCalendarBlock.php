<?php

namespace Drupal\registrar_core\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\registrar_core\SessionColorTrait;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides an 'Academic Calendar' block.
 *
 * @Block(
 *   id = "academic_calendar_block",
 *   admin_label = @Translation("Academic Calendar"),
 *   category = @Translation("Site custom")
 * )
 */
class AcademicCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface, FormInterface, TrustedCallbackInterface {
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui, FormBuilderInterface $formBuilder, RequestStack $requestStack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
    $this->formBuilder = $formBuilder;
    $this->requestStack = $requestStack;
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
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'academic_calendar_filter_form';
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
  public function defaultConfiguration() {
    return [
      'steps' => 0,
      'show_group_by_month' => 1,
      'group_by_month' => 1,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $sessions = [];
    foreach ($this->maui->getSessionsBounded() as $session) {
      $sessions[$session->id] = Html::escape($session->shortDescription);
    }

    $form['steps'] = [
      '#title' => $this->t('Session(s) to display'),
      '#description' => $this->t('What session(s) you wish to display academic calendar information for.'),
      '#type' => 'select',
      '#options' => [
        0 => $this->t('Current session'),
        1 => $this->t('Current session, plus next session'),
        2 => $this->t('Current session, plus next two sessions'),
        3 => $this->t('Current session, plus next three sessions'),
        4 => $this->t('Current session, plus next four sessions'),
        5 => $this->t('Current session, plus next five sessions'),
      ],
      '#default_value' => $this->configuration['steps'],
      '#required' => FALSE,
    ];

    $form['group_by_month'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group by month'),
      '#description' => $this->t('Default setting for grouping events by month.'),
      '#default_value' => $this->configuration['group_by_month'],
    ];
    $form['show_group_by_month'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose "Group by month"'),
      '#description' => $this->t('Expose group by month filter to visitors.'),
      '#default_value' => $this->configuration['show_group_by_month'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['steps'] = $form_state->getValue('steps');
    $this->configuration['show_group_by_month'] = $form_state->getValue('show_group_by_month');
    $this->configuration['group_by_month'] = $form_state->getValue('group_by_month');
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
    $build['#attached']['library'][] = 'sitenow/chosen';
    $build['#attached']['library'][] = 'uids_base/card';
    $build['#attached']['library'][] = 'uids_base/chosen';
    $build['#attached']['library'][] = 'registrar_core/academic-calendar';

    $current = $this->maui->getCurrentSession();
    $steps = $this->configuration['steps'];
    $sessions = $this->maui->getSessionsRange($current->id, max(1, $steps));

    // Get the start date of the first session.
    $first_session_start_date = $sessions[0]->startDate;

    // Get the end date of the last session.
    $last_session_end_date = end($sessions)->endDate;

    // Add the configuration values to drupalSettings.
    $build['#attached']['drupalSettings']['academicCalendar'] = [
      'steps' => $steps,
      'groupByMonth' => $this->configuration['group_by_month'],
      'showGroupByMonth' => $this->configuration['show_group_by_month'],
      'firstSessionStartDate' => $first_session_start_date,
      'lastSessionEndDate' => $last_session_end_date,
    ];

    return $build;
  }

  /**
   * Builds the form elements for the block.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['#id'] = 'academic-calendar-filter-form';
    $form['#attributes']['class'][] = 'academic-calendar-filters sidebar element--padding__all--minimal bg--gray';

    $current_request = $this->requestStack->getCurrentRequest();

    if ($this->configuration['show_group_by_month']) {
      $form['group_by_month'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Group by month'),
        '#id' => 'group-by-month',
        '#default_value' => $this->configuration['group_by_month'],
      ];
    }

    $form['show_previous_events'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show previous events'),
      '#id' => 'show-previous-events',
      '#states' => [
        'visible' => [
          ':input[id="group-by-month"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#attributes' => [
        'placeholder' => $this->t('Search the calendar'),
        'class' => ['academic-calendar-search'],
      ],
    ];

    $form['session'] = [
      '#type' => 'select',
      '#title' => $this->t('Session'),
      '#options' => ['all' => $this->t('All Sessions')],
      '#empty_option' => $this->t('- Select -'),
      '#attributes' => ['class' => ['academic-calendar-session']],
    ];

    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      '#attributes' => ['class' => ['academic-calendar-start-date']],
    ];

    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#attributes' => ['class' => ['academic-calendar-end-date']],
    ];

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => $this->maui->getDateCategories(),
      '#default_value' => $current_request->query->get('category', 'STUDENT'),
      '#multiple' => TRUE,
    ];

    $form['subsession'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show subsessions'),
      '#id' => 'subsession',
      '#default_value' => $current_request->query->get('subsession', FALSE),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    return $form;
  }

  /**
   * A #lazy_builder callback.
   */
  public static function lazyBuilder() {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['academic-calendar', 'content']],
      'content' => [
        '#markup' => '<span class="fa-solid fa-spinner fa-spin"></span>',
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
