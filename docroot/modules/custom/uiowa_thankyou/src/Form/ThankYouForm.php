<?php

namespace Drupal\uiowa_thankyou\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Dispatch-enabled Thank You form.
 */
class ThankYouForm extends FormBase {
  /**
   * The config.storage service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config.factory service.
   * @param \Drupal\Core\Config\StorageInterface $configStorage
   *   The config.storage service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StorageInterface $configStorage) {
    parent::__construct($config_factory);
    $this->configStorage = $configStorage;
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
      '#title' => t('Email address of employee you want to thank'),
      '#required' => TRUE,
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => t('Message'),
    ];
    $form['nominators_name'] = [
      '#type' => 'textfield',
      '#title' => t('Nominator\'s Name'),
      '#required' => TRUE,
    ];
    $form['nominators_email_address'] = [
      '#type' => 'email',
      '#title' => t('Nominator\'s Email Address'),
      '#required' => TRUE,
    ];
    $form['recipient_first_name'] = [
      '#type' => 'textfield',
      '#title' => t('Recipient\'s First Name'),
      '#access' => FALSE,
    ];
    $form['recipient_last_name'] = [
      '#type' => 'textfield',
      '#title' => t('Recipient\'s Last Name'),
      '#access' => FALSE,
    ];
    $form['recipient_hawkid'] = [
      '#type' => 'textfield',
      '#title' => t('Recipient\'s HawkID'),
      '#access' => FALSE,
    ];
    $form['supervisor_first_name'] = [
      '#type' => 'textfield',
      '#title' => t('Supervisor First Name'),
      '#access' => FALSE,
    ];
    $form['supervisor_last_name'] = [
      '#type' => 'textfield',
      '#title' => t('Supervisor Last Name'),
      '#access' => FALSE,
    ];
    $form['supervisor_email'] = [
      '#type' => 'textfield',
      '#title' => t('Supervisor Email'),
      '#access' => FALSE,
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
    $component_id = '';
    $placeholders = [];
    $uiowa_thankyou_settings = $this->config('uiowa_thankyou.settings');

    // Find the component id based upon form_key admin config.
    foreach ($form['#node']->webform['components'] as $cid => $component) {
      if ($component['form_key'] == $uiowa_thankyou_settings->get('uiowa_thankyou_recipient_email_form_component')) {
        $component_id = $cid;
      }
      $placeholders[$cid] = $component['form_key'];
    }
    $recipient_email = $form_state['values']['submitted'][$form['#node']->webform['components'][$component_id]['form_key']];

    // Get HR data.
    $user = $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_user');
    $pass = $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_pass');
    $endpoint = 'https://' . $user . ':' . $pass . '@hris.uiowa.edu/apigateway/oneit/thankyounotes/addressee?email=' . $recipient_email;
    // @todo Update this.
    $request = drupal_http_request($endpoint, array(
      'headers' => array(
        'accept' => 'application/json',
      ),
    ));
    // If the request is successful.
    if ($request->code == '200') {
      $form_state['thankyou_vars'] = array(
        'recipient_email' => $recipient_email,
        // @todo Update this.
        'hr_data' => drupal_json_decode($request->data),
        'component_id' => $component_id,
        'placeholders' => $placeholders,
      );
    }
    else {
      if (isset($request->data)) {
        $message = $request->data;
      }
      else {
        $message = $request->error;
      }
      // @todo Update this.
      form_set_error($form['#node']->webform['components'][$component_id]['name'], $message);
    }
    parent::validateForm($form, $form_state);
  }

}
