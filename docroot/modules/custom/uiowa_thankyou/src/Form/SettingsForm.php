<?php

namespace Drupal\uiowa_thankyou\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Uiowa Thank You settings for this site.
 */
class SettingsForm extends ConfigFormBase {
  use StringTranslationTrait;
  use LoggerChannelTrait;

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
    $api_key = $uiowa_thankyou_settings->get('uiowa_thankyou_dispatch_apikey');
    $endpoint = 'https://apps.its.uiowa.edu/dispatch/api/v1/';

    $form = parent::buildForm($form, $form_state);

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
      '#required' => TRUE,
    ];

    if (!empty($api_key)) {
      $campaign_url = $uiowa_thankyou_settings->get('uiowa_thankyou_dispatch_campaign');
      $campaigns = $this->dispatchGetData($endpoint . 'campaigns', $api_key);

      if ($campaigns instanceof RequestException) {
        $this->logger('uiowa_thankyou')
          ->warning($this->t('Dispatch call failed with error: @message', [
            '@message' => $campaigns->getMessage(),
          ]));
        return $form;
      }

      $campaigns = $this->jsonController->decode($campaigns->getBody()->getContents());
      $options = [
        '0' => 'None',
      ];

      foreach ($campaigns as $campaign) {
        $r = $this->dispatchGetData($campaign, $api_key);
        if ($r instanceof RequestException) {
          $this->logger('uiowa_thankyou')
            ->warning($this->t('Dispatch call failed with error: @message', [
              '@message' => $r->getMessage(),
            ]));
          return $form;
        }
        $d = $this->jsonController->decode($r->getBody()->getContents());
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
        $communications = $this->dispatchGetData($campaign_url . '/communications', $api_key);
        if ($communications instanceof RequestException) {
          $this->logger('uiowa_thankyou')
            ->warning($this->t('Dispatch call failed with error: @message', [
              '@message' => $r->getMessage(),
            ]));
          return $form;
        }

        $communications = $this->jsonController->decode($communications->getBody()->getContents());
        $options = [
          '0' => 'None',
        ];

        foreach ($communications as $communication) {
          $r = $this->dispatchGetData($communication, $api_key);
          if ($r instanceof RequestException) {
            $this->logger('uiowa_thankyou')
              ->warning($this->t('Dispatch call failed with error: @message', [
                '@message' => $r->getMessage(),
              ]));
            return $form;
          }

          $d = $this->jsonController->decode($r->getBody()->getContents());
          $options[$communication] = $d['name'];
        }

        $form['dispatch_fs']['uiowa_thankyou_dispatch_communication'] = [
          '#type' => 'select',
          '#title' => $this->t('Communication'),
          '#default_value' => $uiowa_thankyou_settings->get('uiowa_thankyou_dispatch_communication'),
          '#description' => $this->t('Select the communication to use. Communications are managed in the <a href="https://apps.its.uiowa.edu/dispatch">dispatch interface</a>. Supervisors will receive a copy automatically.'),
          '#options' => $options,
        ];
      }
    }

    $form['placeholder'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dispatch Email Placeholders'),
      '#description' => $this->t('By default, the <a href="@link">1row x 1col curated Dispatch template</a> is used.', [
        '@link' => 'https://apps.its.uiowa.edu/dispatch/help/curatedtemplate/UI%201%20row%20x%201%20col%20-%20Version%202',
      ]),
      '#collapsible' => TRUE,
    ];

    $form['placeholder']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $uiowa_thankyou_settings->get('title') ?? $this->t('Thank You'),
      '#required' => TRUE,
      '#description' => $this->t('Used as the email subject and title.'),
    ];

    $form['placeholder']['unit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('College/Unit'),
      '#default_value' => $uiowa_thankyou_settings->get('unit'),
      '#required' => TRUE,
      '#description' => $this->t('Used in the email header.'),
    ];

    $form['placeholder']['row1_heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading'),
      '#default_value' => $uiowa_thankyou_settings->get('heading'),
      '#required' => TRUE,
      '#description' => $this->t('Used in the email header.'),
    ];

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
      ->set('uiowa_thankyou_dispatch_apikey', $form_state->getValue('uiowa_thankyou_dispatch_apikey'))
      ->set('uiowa_thankyou_title', $form_state->getValue('uiowa_thankyou_title'))
      ->set('uiowa_thankyou_unit', $form_state->getValue('uiowa_thankyou_unit'))
      ->set('uiowa_thankyou_hrapi_user', $form_state->getValue('uiowa_thankyou_hrapi_user'))
      ->set('uiowa_thankyou_hrapi_pass', $form_state->getValue('uiowa_thankyou_hrapi_pass'))
      ->set('uiowa_thankyou_dispatch_campaign', $form_state->getValue('uiowa_thankyou_dispatch_campaign'))
      ->set('uiowa_thankyou_dispatch_communication', $form_state->getValue('uiowa_thankyou_dispatch_communication'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Helper function to get dispatch data.
   *
   * @param string $endpoint
   *   Fully qualified url.
   * @param string $api_key
   *   Api key from Dispatch.
   *
   * @return object
   *   The HTTP response from dispatch.
   */
  protected function dispatchGetData(string $endpoint, string $api_key) {
    try {
      $response = $this->httpClient->get($endpoint, [
        'headers' => [
          'x-dispatch-api-key' => $api_key,
          'accept' => 'application/json',
        ],
      ]);
    }
    catch (RequestException $e) {
      return $e;
    }
    return $response;
  }

}
