<?php

namespace Drupal\sitenow_advanced_webform\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure SiteNow Pages settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'sitenow_advanced_webform.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_advanced_webform_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
      'prospector.site_uuid',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $form = parent::buildForm($form, $form_state);

    $form['prospector']['site_uuid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prospector Website UUID'),
      '#default_value' => $config->get('prospector.site_uuid'),
      '#description' => $this->t('The UUID of the website in the Prospector application.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the Prospector site UUID.
    $this->configFactory->getEditable(static::SETTINGS)
      // Save the featured image display default.
      ->set('prospector.site_uuid', $form_state->getValue('site_uuid'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
