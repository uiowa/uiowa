<?php

namespace Drupal\uiowa_thankyou\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Uiowa Thank You settings for this site.
 */
class SettingsForm extends ConfigFormBase {
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
    $api_key = $uiowa_thankyou_settings->get('api_key');
    $endpoint = 'https://apps.its.uiowa.edu/dispatch/api/v1/';

    $form = parent::buildForm($form, $form_state);

    // Webform Setup.
    $form['webform_fs'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Webform Configuration'),
      '#description' => $this->t('To come later...'),
      '#collapsible' => TRUE,
    ];
    // HR API.
    $form['hr_fs'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('HR API Configuration'),
      '#collapsible' => TRUE,
    ];
    $form['hr_fs']['uiowa_thankyou_hrapi_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_user'),
      '#description' => $this->t('Username to connect to the HR API.'),
      '#required' => TRUE,
    ];
    $form['hr_fs']['uiowa_thankyou_hrapi_pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#attributes' => [
        'value' => $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_pass'),
      ],
      '#description' => $this->t('Password to connect to the HR API.'),
      '#required' => TRUE,
    ];

    $form['dispatch_fs'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dispatch Configuration'),
      '#collapsible' => TRUE,
    ];
    $form['dispatch_fs']['uiowa_thankyou_dispatch_apikey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dispatch API key'),
      '#default_value' => $api_key,
      '#description' => $this->t('Provide an API key from Dispatch client settings.'),
    ];
    if (!empty($apikey)) {
      $campaign_url = $uiowa_thankyou_settings->get('oneit_thankyou_dispatch_campaign');
      $campaigns = $this->dispatchGetData($endpoint . 'campaigns', $apikey);
      $campaigns = $this->jsonController->decode($campaigns->data);
      $options = [
        '0' => 'None',
      ];
      foreach ($campaigns as $campaign) {
        $r = $this->dispatchGetData($campaign, $apikey);
        $d = $this->jsonController->decode($r->data);
        $options[$campaign] = $d['name'];
      }

      $form['dispatch_fs']['uiowa_thankyou_dispatch_campaign'] = [
        '#type' => 'select',
        '#title' => $this->t('Campaign'),
        '#default_value' => $campaign_url,
        '#description' => $this->t('Select a Dispatch campaign.'),
        '#options' => $options,
      ];
      if (!empty($campaign_url)) {
        $communications = $this->dispatchGetData($campaign_url . '/communications', $apikey);
        $communications = $this->jsonController->decode($communications->data);
        $options = [
          '0' => 'None',
        ];
        foreach ($communications as $communication) {
          $r = $this->dispatchGetData($communication, $apikey);
          $d = $this->jsonController->decode($r->data);
          $options[$communication] = $d['name'];
        }

        $form['dispatch_fs']['uiowa_thankyou_dispatch_recipient_communication'] = [
          '#type' => 'select',
          '#title' => $this->t('Recipient Communication'),
          '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_dispatch_recipient_communication'),
          '#description' => $this->t('Select the recipient communication. Communications are managed in the <a href="https://apps.its.uiowa.edu/dispatch">dispatch interface</a>'),
          '#options' => $options,
        ];
        $form['dispatch_fs']['uiowa_thankyou_dispatch_supervisor_communication'] = [
          '#type' => 'select',
          '#title' => $this->t('Supervisor Communication'),
          '#default_value' => $uiowa_thankyou_settings->get('oneit_thankyou_dispatch_supervisor_communication'),
          '#description' => $this->t('Select the supervisor communication. Communications are managed in the <a href="https://apps.its.uiowa.edu/dispatch">dispatch interface</a>'),
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
          '#value' => $this->t('Member attributes can be used in Dispatch templates to dynamically display data. All webform component values will be passed to the template via member attributes. The attribute name will be the key of the webform component. For example webform_component_key can be used in a Dispatch template as ${webform_component_key}. Additional member attributes will be available from the HR API and will be hard-coded. @attr', [
            '@attr' => render($wf_mem_attr),
          ]),
        ];
      }
    }
    return $form;
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
      ->set('api_key', $form_state->getValue('api_key'))
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
   * @param string $endpoint
   *   Fully qualified url.
   * @param string $apikey
   *   Api key from Dispatch.
   *
   * @return object
   *   The HTTP response from dispatch.
   */
  protected function dispatchGetData(string $endpoint, string $apikey) {
    // @todo Try and catch errors here.
    $response = $this->httpClient->get($endpoint, [
      'headers' => [
        'x-dispatch-api-key' => $apikey,
        'accept' => 'application/json',
      ],
    ]);
    return $response;
  }

}
