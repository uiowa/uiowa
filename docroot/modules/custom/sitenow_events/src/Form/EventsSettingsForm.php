<?php

namespace Drupal\sitenow_events\Form;

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
   * The Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\pathauto\AliasCleanerInterface $pathauto_alias_cleaner
   *   The alias cleaner.
   * @param \Drupal\path_alias\AliasRepositoryInterface $aliasRepository
   *   The alias checker.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AliasCleanerInterface $pathauto_alias_cleaner, AliasRepositoryInterface $aliasRepository) {
    parent::__construct($config_factory);
    $this->aliasCleaner = $pathauto_alias_cleaner;
    $this->aliasRepository = $aliasRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('pathauto.alias_cleaner'),
      $container->get('path_alias.repository')
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

    $featured_image_display_default = $config->get('featured_image_display_default');

    $form['event_node']['featured_image_display_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Display featured image'),
      '#description' => $this->t('Set the default behavior for how to display a featured image.'),
      '#options' => [
        'do_not_display' => $this
          ->t('Do not display'),
        'small' => $this
          ->t('Small'),
        'medium' => $this
          ->t('Medium'),
        'large' => $this
          ->t('Large'),
      ],
      '#default_value' => $featured_image_display_default ?: 'large',
    ];

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
    $featured_image_display_default = $form_state->getValue('featured_image_display_default');

    $this->configFactory->getEditable(static::SETTINGS)
      // Save the featured image display default.
      ->set('featured_image_display_default', $featured_image_display_default)
      ->save();

    $this->config('sitenow_events.settings')
      ->set('event_link', $form_state->getValue('sitenow_events_event_link'))
      ->set('single_event_path', $path)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
