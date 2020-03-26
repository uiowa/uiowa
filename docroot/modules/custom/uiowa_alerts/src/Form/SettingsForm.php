<?php

namespace Drupal\uiowa_alerts\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Uiowa Bar settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_alerts_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_alerts.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('uiowa_alerts.settings');

    $form['source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Alert feed source'),
      '#required' => TRUE,
      '#default_value' => $config->get('source'),
      '#options' => [
        'json_test' => $this->t('Test: https://emergency.stage.drupal.uiowa.edu/api/active.json'),
        'json_production' => $this->t('Production: https://emergency.uiowa.edu/api/active.json'),
      ],
      '#description' => $this->t('Select the alert source.'),
    ];

    $form['no_alerts_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('No alerts message'),
      '#format' => 'minimal',
      '#allowed_formats' => [
        'minimal',
      ],
      '#default_value' => $config->get('no_alerts_message'),
      '#description' => $this->t('Optionally provide a message to be displayed when there are no active alerts.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $test = $form_state->getValue(['no_alerts_message', 'value']);
    $this->config('uiowa_alerts.settings')
      ->set('source', $form_state->getValue('source'))
      ->set('no_alerts_message', $form_state->getValue(['no_alerts_message', 'value']))
      ->save();
    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
