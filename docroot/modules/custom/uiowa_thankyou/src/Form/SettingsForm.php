<?php

namespace Drupal\uiowa_thankyou\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Uiowa Thank You settings for this site.
 */
class SettingsForm extends ConfigFormBase {
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
    return 'uiowa_thankyou_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_thankyou.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $uiowa_thankyou_settings = $this->config('uiowa_thankyou.settings');
    $webform_nid = $uiowa_thankyou_settings->get('webform_nid');
    $api_key = $uiowa_thankyou_settings->get('api_key');
    $endpoint = 'https://apps.its.uiowa.edu/dispatch/api/v1/';

    // Webform Setup.
    $form['webform_fs'] = [
      '#type' => 'fieldset',
      '#title' => t('Webform Configuration'),
      '#collapsible' => TRUE,
    ];
    $form['webform_fs']['uiowa_thankyou_webform_id'] = [
      '#type' => 'textfield',
      '#title' => t('Webform Node ID'),
      '#default_value' => $webform_nid,
      '#description' => t('Provide the node id of the thank you webform.'),
      '#required' => TRUE,
    ];
    // Only add form field if we have a node id.
    if (!empty($webform_nid)) {
      // @todo Update this...
      $webform = node_load($webform_nid);
      $options = [];
      foreach ($webform->webform['components'] as $component) {
        $options[$component['form_key']] = $component['name'];
      }
      $form['webform_fs']['uiowa_thankyou_recipient_email_form_component'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => 'Recipient Email Component',
        '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_recipient_email_form_component'),
        '#description' => t('Select the component that will be used for the recipient email.'),
        '#required' => TRUE,
      ];
      $form['webform_fs']['uiowa_thankyou_recipient_first_name_form_component'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => 'Recipient First Name Component',
        '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_recipient_first_name_form_component'),
        '#description' => t('Select the component that will be used for the recipient first name.'),
        '#required' => TRUE,
      ];
      $form['webform_fs']['uiowa_thankyou_recipient_last_name_form_component'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => 'Recipient Last Name Component',
        '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_recipient_last_name_form_component'),
        '#description' => t('Select the component that will be used for the recipient last name.'),
        '#required' => TRUE,
      ];
      $form['webform_fs']['uiowa_thankyou_recipient_hawkid_form_component'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => 'Recipient HawkID Component',
        '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_recipient_hawkid_form_component'),
        '#description' => t('Select the component that will be used for the recipient hawkid.'),
        '#required' => TRUE,
      ];
      $form['webform_fs']['uiowa_thankyou_supervisor_first_name_form_component'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => 'Supervisor First Name Component',
        '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_supervisor_first_name_form_component'),
        '#description' => t('Select the component that will be used for the supervisor first name.'),
        '#required' => TRUE,
      ];
      $form['webform_fs']['uiowa_thankyou_supervisor_last_name_form_component'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => 'Supervisor Last Name Component',
        '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_supervisor_last_name_form_component'),
        '#description' => t('Select the component that will be used for the supervisor last name.'),
        '#required' => TRUE,
      ];
      $form['webform_fs']['uiowa_thankyou_supervisor_email_form_component'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => 'Supervisor Email Component',
        '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_supervisor_email_form_component'),
        '#description' => t('Select the component that will be used for the supervisor email.'),
        '#required' => TRUE,
      ];
    }

    // HR API.
    $form['hr_fs'] = [
      '#type' => 'fieldset',
      '#title' => t('HR API Configuration'),
      '#collapsible' => TRUE,
    ];
    $form['hr_fs']['uiowa_thankyou_hrapi_user'] = [
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_user'),
      '#description' => t('Username to connect to the HR API.'),
      '#required' => TRUE,
    ];
    $form['hr_fs']['uiowa_thankyou_hrapi_pass'] = [
      '#type' => 'password',
      '#title' => t('Password'),
      '#attributes' => [
        'value' => $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_pass')
      ],
      '#description' => t('Password to connect to the HR API.'),
      '#required' => TRUE,
    ];

    $form['dispatch_fs'] = [
      '#type' => 'fieldset',
      '#title' => t('Dispatch Configuration'),
      '#collapsible' => TRUE,
    ];
    $form['dispatch_fs']['uiowa_thankyou_dispatch_apikey'] = [
      '#type' => 'textfield',
      '#title' => t('Dispatch API key'),
      '#default_value' => $api_key,
      '#description' => t('Provide an API key from Dispatch client settings.'),
    ];
    if (!empty($apikey)) {
      $campaign_url = $uiowa_thankyou_settings->get('oneit_thankyou_dispatch_campaign');
      // @todo Update these.
      $campaigns = $this->_dispatch_get_data($endpoint . 'campaigns', $apikey);
      $campaigns = drupal_json_decode($campaigns->data);
      $options = array('0' => 'None');
      foreach ($campaigns as $campaign) {
        $r = $this->_dispatch_get_data($campaign, $apikey);
        $d = drupal_json_decode($r->data);
        $options[$campaign] = $d['name'];
      }

      $form['dispatch_fs']['uiowa_thankyou_dispatch_campaign'] = [
        '#type' => 'select',
        '#title' => t('Campaign'),
        '#default_value' => $campaign_url,
        '#description' => t('Select a Dispatch campaign.'),
        '#options' => $options,
      ];
      if (!empty($campaign_url)) {
        // @todo Update these.
        $communications = $this->_dispatch_get_data($campaign_url . '/communications', $apikey);
        $communications = drupal_json_decode($communications->data);
        $options = array('0' => 'None');
        foreach ($communications as $communication) {
          $r = $this->_dispatch_get_data($communication, $apikey);
          $d = drupal_json_decode($r->data);
          $options[$communication] = $d['name'];
        }

        $form['dispatch_fs']['uiowa_thankyou_dispatch_recipient_communication'] = [
          '#type' => 'select',
          '#title' => t('Recipient Communication'),
          '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_dispatch_recipient_communication'),
          '#description' => t('Select the recipient communication. Communications are managed in the <a href="https://apps.its.uiowa.edu/dispatch">dispatch interface</a>'),
          '#options' => $options,
        ];
        $form['dispatch_fs']['uiowa_thankyou_dispatch_supervisor_communication'] = [
          '#type' => 'select',
          '#title' => t('Supervisor Communication'),
          '#default_value' => $uiowa_thankyou_settings->get('oneit_thankyou_dispatch_supervisor_communication'),
          '#description' => t('Select the supervisor communication. Communications are managed in the <a href="https://apps.its.uiowa.edu/dispatch">dispatch interface</a>'),
          '#options' => $options,
        ];
        $form['dispatch_fs']['help'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'messages',
              'warning',
            ],
          ],
        ];
        $form['dispatch_fs']['help']['member_attributes_help'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => t('Member attributes can be used in Dispatch templates to dynamically display data. All webform component values will be passed to the template via member attributes. The attribute name will be the key of the webform component. For example webform_component_key can be used in a Dispatch template as ${webform_component_key}. Additional member attributes will be available from the HR API and will be hard-coded. @attr', array('@attr' => render($wf_mem_attr))),
        ];
        if (!empty($webform_nid)) {
          $items = [];
          foreach ($webform->webform['components'] as $c) {
            $items[] = $c['form_key'];
          }
          $form['dispatch_fs']['help']['member_attributes'] = [
            '#theme' => 'item_list',
            '#items' => $items,
            '#title' => t('Webform Component Keys'),
          ];
        }
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('uiowa_thankyou.settings')
      ->set('webform_nid', $form_state->getValue('webform_nid'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('uiowa_thankyou_recipient_email_form_component', $form_state->getValue('uiowa_thankyou_recipient_email_form_component'))
      ->set('uiowa_thankyou_recipient_first_name_form_component', $form_state->getValue('uiowa_thankyou_recipient_first_name_form_component'))
      ->set('uiowa_thankyou_recipient_last_name_form_component', $form_state->getValue('uiowa_thankyou_recipient_last_name_form_component'))
      ->set('uiowa_thankyou_recipient_hawkid_form_component', $form_state->getValue('uiowa_thankyou_recipient_hawkid_form_component'))
      ->set('uiowa_thankyou_supervisor_first_name_form_component', $form_state->getValue('uiowa_thankyou_supervisor_first_name_form_component'))
      ->set('uiowa_thankyou_supervisor_last_name_form_component', $form_state->getValue('uiowa_thankyou_supervisor_last_name_form_component'))
      ->set('uiowa_thankyou_supervisor_email_form_component', $form_state->getValue('uiowa_thankyou_supervisor_email_form_component'))
      ->set('uiowa_thankyou_hrapi_user', $form_state->getValue('uiowa_thankyou_hrapi_user'))
      ->set('uiowa_thankyou_hrapi_pass', $form_state->getValue('uiowa_thankyou_hrapi_pass'))
      ->set('oneit_thankyou_dispatch_campaign', $form_state->getValue('oneit_thankyou_dispatch_campaign'))
      ->set('uiowa_thankyou_dispatch_recipient_communication', $form_state->getValue('uiowa_thankyou_dispatch_recipient_communication'))
      ->set('oneit_thankyou_dispatch_supervisor_communication', $form_state->getValue('oneit_thankyou_dispatch_supervisor_communication'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Helper function to get dispatch data.
   *
   * @param $endpoint
   *   Fully qualified url.
   *
   * @param $apikey
   *   Api key from Dispatch.
   *
   * @return object
   */
  function _dispatch_get_data($endpoint, $apikey) {
    $response = \Drupal::httpClient()->get($endpoint, [
      'headers' => [
        'x-dispatch-api-key' => $apikey,
        'accept' => 'application/json',
      ],
    ]);
    return $response;
  }

}
