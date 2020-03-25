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
      '#default_value' => $config->get('uiowa_alerts.source'),
      '#options' => [
        'json_test' => 'Test: https://emergency.stage.drupal.uiowa.edu/api/active.json',
        'json_production' => 'Production: https://emergency.uiowa.edu/api/active.json',
      ],
      '#description' => t('Select the alert source.'),
    ];

    $form['no_alerts_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('No alerts message'),
      '#format' => 'minimal',
      '#default_value' => $config->get('uiowa_alerts.no_alerts_messsage'),
      '#description' => $this->t('Optionally provide a message to be displayed when there are no active alerts. Allowed HTML tags: a, p, div, em, strong.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('uiowa_alerts.settings')
      ->set('uiowa_alerts.source', $form_state->getValue('source'))
      ->set('uiowa_alerts.no_alerts_messsage', $form_state->getValue('no_alerts_messsage'))
      ->save();
    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
