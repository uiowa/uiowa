<?php

namespace Drupal\safety_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides configuration form for Safety Core settings.
 */
class SafetyCoreSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'safety_core_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'safety_core.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Request $request = NULL) {
    $config = $this->config('safety_core.settings');

    $form['#attributes']['autocomplete'] = 'off';
    $form['clery_api'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CleryEdge API Settings'),
    ];

    $form['clery_api']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('clery_api.api_key'),
      '#description' => $this->t('The API key for authenticating with the CleryEdge API.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $api_key = $form_state->getValue('api_key');
    if (empty($api_key)) {
      $form_state->setErrorByName('api_key', $this->t('API key cannot be empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('safety_core.settings');
    $config
      ->set('clery_api.api_key', $form_state->getValue('api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
