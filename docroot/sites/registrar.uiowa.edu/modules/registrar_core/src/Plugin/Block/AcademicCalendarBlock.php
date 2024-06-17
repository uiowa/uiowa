<?php

namespace Drupal\registrar_core\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\registrar_core\SessionColorTrait;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an 'Academic Calendar' block.
 *
 * @Block(
 *   id = "academic_calendar_block",
 *   admin_label = @Translation("Academic Calendar"),
 *   category = @Translation("Site custom")
 * )
 */
class AcademicCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface, FormInterface {
  use SessionColorTrait;

  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uiowa_maui.api')
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
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

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
      ],
      '#default_value' => $this->configuration['steps'],
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['steps'] = $form_state->getValue('steps');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm($this);

    $build = [];
    // Add the legend.
    $build['legend'] = $this->buildLegend();
    $build['form'] = $form;
    $build['calendar'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['academic-calendar'], 'id' => 'academic-calendar-wrapper'],
    ];

    // Attach the library for the calendar.
    $build['#attached']['library'][] = 'registrar_core/academic-calendar';
    $build['#attached']['library'][] = 'sitenow/chosen';

    // Add the steps value to drupalSettings.
    $build['#attached']['drupalSettings']['academicCalendar']['steps'] = $this->configuration['steps'];

    return $build;
  }

  /**
   * Builds the legend for the calendar.
   *
   * @return array
   *   A render array for the legend.
   */
  protected function buildLegend() {
    $current = $this->maui->getCurrentSession();
    $steps = $this->configuration['steps'];
    $sessions = $this->maui->getSessionsRange($current->id, max(1, $steps));

    $legend_items = [];
    foreach ($sessions as $index => $session) {
      $bgColor = $this->getSessionColor($index);
      $class = [
        'uiowa-maui-key',
        'uiowa-maui-key-' . $session->id,
        'label',
        'label-' . $bgColor,
        Html::getClass($session->shortDescription),
      ];

      $legend_items[] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $session->shortDescription,
        '#attributes' => [
          'class' => $class,
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['uiowa-maui-legend']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Legend'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $legend_items,
        '#attributes' => ['class' => ['legend-items']],
      ],
    ];
  }

  /**
   * Builds the form elements for the block.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['#id'] = 'academic-calendar-filter-form';
    $form['#attributes']['class'][] = 'academic-calendar-filters';

    $categories = uiowa_maui_category_options();
    $flattened_options = [];
    foreach ($categories as $group => $options) {
      foreach ($options as $key => $value) {
        $flattened_options[$key] = $value;
      }
    }

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => $flattened_options,
      '#default_value' => \Drupal::request()->query->get('category', 'STUDENT'),
      '#multiple' => TRUE,
    ];

    $form['subsession'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show subsessions'),
      '#default_value' => \Drupal::request()->query->get('subsession', FALSE),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlClass($string) {
    // Convert the string to a valid HTML class.
    return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $string));
  }

  /**
   * {@inheritdoc}
   */
  public function getCalendarEvents(Request $request) {
    $category = $request->query->get('category', ['STUDENT']);
    $subsession = $request->query->get('subsession', 0);

    // Ensure category is always an array.
    if (!is_array($category)) {
      $category = [$category];
    }

    // Fetch and filter events based on category and subsession.
    $events = $this->fetchAndFilterEvents($category, $subsession);

    return new JsonResponse($events);
  }

  /**
   * {@inheritdoc}
   */
  private function fetchAndFilterEvents($categories, $subsession) {
    $allEvents = $this->maui->getAllEvents();

    $filteredEvents = array_filter($allEvents, function ($event) use ($categories, $subsession) {
      $categoryMatch = empty($categories) || in_array($event['category'], $categories);
      $subsessionMatch = $subsession ? $event['is_subsession'] : TRUE;
      return $categoryMatch && $subsessionMatch;
    });

    return $filteredEvents;
  }

  /**
   * AJAX callback to update the calendar.
   */
  public function updateCalendar(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $category = $form_state->getValue('category');
    $subsession = $form_state->getValue('subsession');

    $response->addCommand(new InvokeCommand('.academic-calendar', 'fullCalendar', ['refetchEvents']));

    // Pass the filter values to JavaScript.
    $response->addCommand(new InvokeCommand(NULL, 'updateCalendarFilters', [$category, $subsession]));

    $debug_message = "Filters updated - Category: " . implode(', ', $category) . ", Subsession: " . ($subsession ? 'Yes' : 'No');
    $response->addCommand(new HtmlCommand('.form-debug', $debug_message));

    return $response;
  }

}
