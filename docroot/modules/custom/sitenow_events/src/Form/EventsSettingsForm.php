<?php

namespace Drupal\sitenow_events\Form;

use Drupal\config_split\ConfigSplitManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure events settings for this site.
 */
class EventsSettingsForm extends ConfigFormBase {

  /**
   * The alias cleaner.
   *
   * @var \Drupal\pathauto\AliasCleanerInterface
   */
  protected $aliasCleaner;

  /**
   * The alias checker.
   *
   * @var \Drupal\path_alias\AliasRepositoryInterface
   */
  protected $aliasRepository;

  /**
   * The config split manager.
   *
   * @var \Drupal\config_split\ConfigSplitManager
   */
  protected $configSplitManager;

  /**
   * The Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\pathauto\AliasCleanerInterface $pathauto_alias_cleaner
   *   The alias cleaner.
   * @param \Drupal\path_alias\AliasRepositoryInterface $aliasRepository
   *   The alias checker.
   * @param \Drupal\config_split\ConfigSplitManager $configSplitManager
   *   The config split manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AliasCleanerInterface $pathauto_alias_cleaner, AliasRepositoryInterface $aliasRepository, ConfigSplitManager $configSplitManager) {
    parent::__construct($config_factory);
    $this->aliasCleaner = $pathauto_alias_cleaner;
    $this->aliasRepository = $aliasRepository;
    $this->configSplitManager = $configSplitManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('pathauto.alias_cleaner'),
      $container->get('path_alias.repository'),
      $container->get('config_split.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_events_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sitenow_events.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('sitenow_events.settings');

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings let you configure the SiteNow Events module.</p>'),
    ];

    $form['global'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site-wide settings'),
      '#description' => $this->t('These settings affect all event lists and single instances.'),
    ];

    $form['global']['sitenow_events_event_link'] = [
      '#type' => 'select',
      '#title' => $this->t('Link Option'),
      '#default_value' => $config->get('event_link'),
      '#description' => $this->t('Choose to have events link to events.uiowa.edu or an event page on this site.'),
      '#options' => [
        'event-link-external' => $this->t('Link to events.uiowa.edu'),
        'event-link-internal' => $this->t('Link to page on this site'),
      ],
    ];

    $form['global']['sitenow_events_single_event_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Single event path'),
      '#description' => $this->t('The base path component for a single event. Defaults to <em>event</em>.'),
      '#default_value' => $config->get('single_event_path'),
      '#required' => TRUE,
    ];

    $config_split = $this->configSplitManager->getSplitConfig('config_split.config_split.event');
    if (isset($config_split) && $config_split->get('status')) {
      $form['sitenow_events_filter'] = [
        '#type' => 'fieldset',
        '#title' => 'Upcoming Events Filters',
        '#description' => $this->t('These settings affect locally created events and their lists.'),
        '#collapsible' => FALSE,
      ];
      $form['sitenow_events_filter']['filter_date_range'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Date Range'),
        '#description' => $this->t('Allow filtering by the date range'),
        '#default_value' => $config->get('filter_display.date_range'),
        '#size' => 60,
      ];
      $form['sitenow_events_filter']['filter_presenters'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Presenters'),
        '#description' => $this->t('Allow filtering by presenter'),
        '#default_value' => $config->get('filter_display.presenters'),
        '#size' => 60,
      ];
      $form['sitenow_events_filter']['filter_attendance_required'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Attendance Required'),
        '#description' => $this->t('Allow filtering by attendance requirement'),
        '#default_value' => $config->get('filter_display.attendance_required'),
        '#size' => 60,
      ];
      $form['sitenow_events_filter']['filter_attendance_mode'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Attendance type'),
        '#description' => $this->t('Allow filtering by attendance type'),
        '#default_value' => $config->get('filter_display.attendance_mode'),
        '#size' => 60,
      ];
      $form['sitenow_events_filter']['filter_category'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Category'),
        '#description' => $this->t('Allow filtering by category'),
        '#default_value' => $config->get('filter_display.category'),
        '#size' => 60,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Check if path already exists.
    $path = $form_state->getValue('sitenow_events_single_event_path');
    // Clean up path first.
    $path = $this->aliasCleaner->cleanString($path);
    $path_exists = $this->aliasRepository->lookupByAlias('/' . $path, 'en');
    if ($path_exists) {
      $form_state->setErrorByName('path', $this->t('This path is already in-use.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $path = $form_state->getValue('sitenow_events_single_event_path');
    // Clean path.
    $path = $this->aliasCleaner->cleanString($path);

    $this->config('sitenow_events.settings')
      ->set('event_link', $form_state->getValue('sitenow_events_event_link'))
      ->set('single_event_path', $path)
      ->save();

    $config_split = $this->configSplitManager->getSplitConfig('config_split.config_split.event');
    if (isset($config_split) && $config_split->get('status')) {
      $filters = [];
      foreach ([
        'date_range',
        'presenters',
        'attendance_required',
        'attendance_mode',
        'category',
      ] as $filter_option) {
        $filters[$filter_option] = $form_state->getValue("filter_{$filter_option}");
      }
      $this->config('sitenow_events.settings')
        ->set('filter_display', $filters)
        ->save();
    }

    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
