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

    $form['hawk_alert'] = [
      '#type' => 'markup',
      '#title' => $this->t('Hawk Alerts'),
      '#markup' => $this->t('Active Hawk Alerts from <a href="@link">emergency.uiowa.edu</a>  will be automatically displayed at the top of every page.', [
        '@link' => 'https://emergency.uiowa.edu',
      ]),
    ];

    $form['custom_alert_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Custom Alert'),
      '#default_value' => $config->get('custom_alert.display'),
      '#description' => $this->t('Check to display a custom alert at the top of every page.'),
      '#attributes' => [
        'name' => 'custom_alert_display',
      ],
      '#return_value' => TRUE,
    ];

    $form['custom_alert_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Custom Alert Level'),
      '#options' => [
        'info' => $this->t('Info'),
        'warning' => $this->t('Warning'),
        'danger' => $this->t('Danger'),
      ],
      '#default_value' => $config->get('custom_alert.level'),
      '#description' => $this->t('The custom alert level. Determines the color of the alert based on the <a href="@link">UIDS</a>.', [
        '@link' => 'https://uiowa.github.io/uids/components/detail/alerts--info.html',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="custom_alert_display"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="custom_alert_display"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // @todo: Figure out why required state results in console error on submit.
    $form['custom_alert_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Custom Alert Message'),
      '#format' => 'minimal',
      '#allowed_formats' => [
        'minimal',
      ],
      '#default_value' => $config->get('custom_alert.message'),
      '#description' => $this->t('The message to be displayed. The first heading in the message will have an associated icon based on the alert level.'),
      '#states' => [
        'visible' => [
          ':input[name="custom_alert_display"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('uiowa_alerts.settings')
      ->set('custom_alert.display', $form_state->getValue('custom_alert_display'))
      ->set('custom_alert.level', $form_state->getValue('custom_alert_level'))
      ->set('custom_alert.message', $form_state->getValue([
        'custom_alert_message',
        'value',
      ]))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
