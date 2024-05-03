<?php

namespace Drupal\its_core\Form;

use Drupal\Component\Utility\EmailValidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure ITS Core settings.
 */
class ItsCoreSettingsForm extends ConfigFormBase {

  /**
   * The email.validator service.
   *
   * @var Drupal\Component\Utility\EmailValidator
   */
  protected $emailValidator;

  /**
   * Form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config.factory service.
   * @param \Drupal\Component\Utility\EmailValidator $email_validator
   *   The email.validator service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EmailValidator $email_validator) {
    parent::__construct($config_factory);
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('email.validator'),
    );
  }

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
      '#title' => $this->t('Alert email settings'),
      'single-alert-to' => [
        // @todo Update to a multiple email field when available.
        //   https://www.drupal.org/project/drupal/issues/3214029.
        '#type' => 'textfield',
        '#title' => $this->t('Alert notification'),
        '#description' => $this->t('Emails to which individual alert notifications will be sent. Multiple emails should be separated by a comma.'),
        '#default_value' => $this->config('its_core.settings')->get('single-alert-to') ?? '',
      ],
      'single-alert-bcc' => [
        // @todo Update to a multiple email field when available.
        //   https://www.drupal.org/project/drupal/issues/3214029.
        '#type' => 'textfield',
        '#title' => $this->t('Alert notification BCC'),
        '#description' => $this->t('Emails to which individual alert notifications will include as BCCs. Multiple emails should be separated by a comma.'),
        '#default_value' => $this->config('its_core.settings')->get('single-alert-bcc') ?? '',
      ],
      'alert-digest' => [
        // @todo Update to a multiple email field when available.
        //   https://www.drupal.org/project/drupal/issues/3214029.
        '#type' => 'textfield',
        '#title' => $this->t('Alert digest'),
        '#description' => $this->t('Emails to which the daily alert digest email will be sent. Multiple emails should be separated by a comma.'),
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
        if ($email !== '' && !$this->emailValidator->isValid($email)) {
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
