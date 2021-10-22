<?php

namespace Drupal\uiowa_thankyou\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Dispatch-enabled Thank You form.
 */
class ThankYouForm extends FormBase {
  use StringTranslationTrait;

  /**
   * The config.storage service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The serialization.json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $jsonController;

  /**
   * The HTTP Client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->configStorage = $container->get('config.storage');
    $instance->jsonController = $container->get('serialization.json');
    $instance->httpClient = $container->get('http_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_thankyou_thankyou_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email_address_of_the_person_to_thank'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address of employee you want to thank'),
      '#description' => $this->t('Look up an email address in our <a target="_blank" href="https://iam.uiowa.edu/whitepages/search">directory</a>.'),
      '#required' => TRUE,
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
    ];
    $form['nominators_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Nominator's Name"),
      '#required' => TRUE,
    ];
    $form['nominators_email_address'] = [
      '#type' => 'email',
      '#title' => $this->t("Nominator's Email Address"),
      '#required' => TRUE,
    ];
    $form['recipient_first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Recipient's First Name"),
      '#access' => FALSE,
    ];
    $form['recipient_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Recipient's Last Name"),
      '#access' => FALSE,
    ];
    $form['recipient_hawkid'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Recipient's HawkID"),
      '#access' => FALSE,
    ];
    $form['supervisor_first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Supervisor First Name'),
      '#access' => FALSE,
    ];
    $form['supervisor_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Supervisor Last Name'),
      '#access' => FALSE,
    ];
    $form['supervisor_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Supervisor Email'),
      '#access' => FALSE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // no-op.
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Set vars.
    $placeholders = [];
    $uiowa_thankyou_settings = $this->config('uiowa_thankyou.settings');

    $recipient_email = $form_state->getValue('email_address_of_the_person_to_thank');

    // Get HR data.
    $user = $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_user');
    $pass = $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_pass');
    $endpoint = 'https://' . $user . ':' . $pass . '@hris.uiowa.edu/apigateway/oneit/thankyounotes/addressee?email=' . $recipient_email;
    // @todo Try and catch errors here.
    $request = $this->httpClient->get($endpoint, [
      'headers' => [
        'accept' => 'application/json',
      ],
    ]);
    // If the request is successful.
    if ($request->code == '200') {
      $form_state['thankyou_vars'] = [
        'recipient_email' => $recipient_email,
        'hr_data' => $this->jsonController->decode($request->data),
        'placeholders' => $placeholders,
      ];
    }
    else {
      if (isset($request->data)) {
        $message = $request->data;
      }
      else {
        $message = $request->error;
      }
      $form_state->setError($form, $message);
    }
    parent::validateForm($form, $form_state);
  }

}
