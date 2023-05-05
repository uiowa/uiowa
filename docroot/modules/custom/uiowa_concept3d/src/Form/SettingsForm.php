<?php

namespace Drupal\uiowa_concept3d\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Uiowa Concept3D settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_concept3d_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_concept3d.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('uiowa_concept3d.settings');



    $form['gtag']['uiowa_concept3d_api'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Concept3D API Key'),
      '#default_value' => $config->get('uiowa_concept3d.api_key'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('uiowa_concept3d.settings')
      ->set('uiowa_concept3d.api_key', $form_state->getValue('uiowa_concept3d_api'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
