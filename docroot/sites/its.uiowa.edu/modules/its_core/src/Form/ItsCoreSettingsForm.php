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
      'single-alert-to' => [
        // @todo Update to a multiple email field when available.
        //   https://www.drupal.org/project/drupal/issues/3214029.
        '#type' => 'textfield',
        '#title' => $this->t('Alert notification'),
        '#description' => $this->t('Emails to which individual alert notifications should be sent. Multiple emails should be separated by a comma.'),
        '#default_value' => $this->config('its_core.settings')->get('single-alert-to') ?? '',
      ],
      'single-alert-bcc' => [
        // @todo Update to a multiple email field when available.
        //   https://www.drupal.org/project/drupal/issues/3214029.
        '#type' => 'textfield',
        '#title' => $this->t('Alert notification BCC'),
        '#description' => $this->t('Emails to which individual alert notifications should include as BCCs. Multiple emails should be separated by a comma.'),
        '#default_value' => $this->config('its_core.settings')->get('single-alert-bcc') ?? '',
      ],
      'alert-digest' => [
        // @todo Update to a multiple email field when available.
        //   https://www.drupal.org/project/drupal/issues/3214029.
        '#type' => 'textfield',
        '#title' => $this->t('Alert digest email'),
        '#description' => $this->t('Email to which the daily alert digest email will be sent. Multiple emails should be separated by a comma.'),
        '#default_value' => $this->config('its_core.settings')->get('alert-digest') ?? '',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Basic email validation for each email in the comma-delimited string,
    // based on https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Element%21Email.php/function/Email%3A%3AvalidateEmail.
    foreach (['single-alert-to',
      'single-alert-bcc',
      'alert-digest',
    ] as $fieldname) {
      $value = trim($form_state->getValue($fieldname));
      $emails = explode(',', $value);
      $form_state->setValue($fieldname, $value);
      foreach ($emails as $email) {
        // @todo Update this to use dependency injection instead.
        if ($email !== '' && !\Drupal::service('email.validator')->isValid($email)) {
          $form_state
            ->setError($form, $this->t('The email address %mail is not valid.', [
              '%mail' => $email,
            ]));
          break;
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('its_core.settings')
      ->set('single-alert-to', $form_state->getValue('single-alert-to'))
      ->set('single-alert-bcc', $form_state->getValue('single-alert-bcc'))
      ->set('alert-digest', $form_state->getValue('alert-digest'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
