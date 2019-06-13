<?php

namespace Drupal\uiowa_events\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure UIowa Events settings for this site.
 */
class UIEventsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_events_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_events.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('uiowa_events.settings');

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings let you configure the University of Iowa Events module.</p>'),
    ];

    $form['global'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site-wide settings'),
      '#description' => $this->t('These settings affect all University of Iowa event lists and single instances.'),
    ];

    $form['global']['uiowa_events_event_link'] = [
      '#type' => 'select',
      '#title' => $this->t('Link Option'),
      '#default_value' => $config->get('uiowa_events.event_link'),
      '#description' => $this->t('Choose to have events link to events.uiowa.edu or an event page on this site.'),
      '#options' => [
        'event-link-external' => $this->t('Link to events.uiowa.edu'),
        'event-link-internal' => $this->t('Link to page on this site'),
      ],
    ];

    $form['global']['uiowa_events_cache_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Events Caching'),
      '#default_value' => $config->get('uiowa_events.cache_time'),
      '#description' => $this->t('Enter the number of minutes event data should be cached. (Minimum of 5 minutes)'),
      '#size' => 60,
      '#required' => TRUE,
    ];

    $form['global']['uiowa_events_single_event_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Single event path'),
      '#description' => $this->t('The base path component for a single event. Defaults to <em>event</em>.'),
      '#default_value' => $config->get('uiowa_events.single_event_path'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Check if path already exists.
    $path = $form_state->getValue('uiowa_events_single_event_path');
    // Clean up path first.
    $path = \Drupal::service('pathauto.alias_cleaner')->cleanString($path);
    $path_exists = \Drupal::service('path.alias_storage')->aliasExists('/' . $path, 'en');
    if ($path_exists) {
      $form_state->setErrorByName('path', $this->t('This path is already in-use.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $path = $form_state->getValue('uiowa_events_single_event_path');
    // Clean path.
    $path = \Drupal::service('pathauto.alias_cleaner')->cleanString($path);

    $this->config('uiowa_events.settings')
      ->set('uiowa_events.event_link', $form_state->getValue('uiowa_events_event_link'))
      ->set('uiowa_events.cache_time', $form_state->getValue('uiowa_events_cache_time'))
      ->set('uiowa_events.single_event_path', $path)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
