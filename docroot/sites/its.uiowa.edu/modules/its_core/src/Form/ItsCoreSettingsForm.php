<?php

namespace Drupal\its_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure ITS Core settings.
 */
class ItsCoreSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'its_core_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['its_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Alert email settings.'),
      'alert-notification' => [
        '#type' => 'email',
        '#title' => $this->t('Alert notification'),
        '#description' => $this->t('Notification email that is sent upon alert creation.'),
        '#default_value' => $this->config('its_core.settings')->get('alert-notification') ?? '',
      ],
      'alert-digest' => [
        '#type' => 'email',
        '#title' => $this->t('Alert digest email'),
        '#description' => $this->t('The daily alert digest email.'),
        '#default_value' => $this->config('its_core.settings')->get('alert-digest') ?? '',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('its_core.settings')
      ->set('alert-notification', $form_state->getValue('alert-notification'))
      ->set('alert-digest', $form_state->getValue('alert-digest'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
