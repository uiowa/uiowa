<?php

namespace Drupal\uiowa_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Uiowa Core settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_core_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('uiowa_core.settings');

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => t('<p>These settings allow you to configure certain aspects of this website.</p>'),
    ];

    $form['gtag'] = [
      '#type' => 'fieldset',
      '#title' => 'Google Tag Manager',
      '#collapsible' => FALSE,
    ];

    $form['gtag']['uiowa_core_gtag'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Google Tag Manager Functionality'),
      '#default_value' => $config->get('uiowa_core.gtag'),
      '#description' => $this->t('If checked, and Google Tag Manager containers are configured, container snippets will be inserted and loaded on the website.'),
      '#size' => 60,
    ];
    if (!sitenow_is_user_admin(\Drupal::currentUser())) {
      $form['gtag']['uiowa_core_gtag']['#access'] = FALSE;
      $form['gtag']['markup'] = [
        '#type' => 'markup',
        '#markup' => t('<p><em>Only administrators can control whether the Google Tag Functionality is enabled/disabled.</em></p>'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('uiowa_core.settings')
      ->set('uiowa_core.gtag', $form_state->getValue('uiowa_core_gtag'))
      ->save();
    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
