<?php

namespace Drupal\uiowa_thankyou\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Xss;

/**
 * Provides a Dispatch-enabled Thank You form.
 */
class ThankYouForm extends FormBase {
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Set vars. Placeholders can be used for extended dispatch templates.
    $placeholders = [
      'message' => $form_state->getValue('message'),
    ];
    $uiowa_thankyou_settings = $this->config('uiowa_thankyou.settings');

    $recipient_email = $form_state->getValue('email_address_of_the_person_to_thank');

    // Get HR data.
    $user = $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_user');
    $pass = $uiowa_thankyou_settings->get('uiowa_thankyou_hrapi_pass');
    $endpoint = 'https://' . $user . ':' . $pass . '@hris.uiowa.edu/apigateway/oneit/thankyounotes/addressee?email=' . $recipient_email;
    // @todo Clean up the error handling here.
    try {
      $request = $this->httpClient->get($endpoint, [
        'headers' => [
          'accept' => 'application/json',
        ],
      ]);
    }
    catch (RequestException | GuzzleException | ClientException $e) {
      $this->logger('uiowa_thankyou')->error($this->t('HR API error: @error.', [
        '@error' => $e->getMessage()
      ]));

      $form_state->setError($form, $this->t('An error was encountered processing the form. If the problem persists, please contact the ITS Help Desk.'));
    }

    // @todo: If local env, continue with dummy HR data?
    if (!isset($request)) {
      return;
    }

    // If the request is successful.
    if ($request->code == '200') {
      $form_state['thankyou_vars'] = [
        'recipient_email' => $recipient_email,
        'hr_data' => $this->jsonController->decode($request->getBody()->getContents()),
        'placeholders' => $placeholders,
      ];
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $placeholders = $form_state['thankyou_vars']['placeholders'];
    $recipient_email = $form_state['thankyou_vars']['recipient_email'];
    $hr_data = $form_state['thankyou_vars']['hr_data'];

    $uiowa_thankyou_settings = $this->config('uiowa_thankyou.settings');
    $apikey = $uiowa_thankyou_settings->get('uiowa_thankyou_dispatch_apikey');
    $endpoint = $uiowa_thankyou_settings->get('uiowa_thankyou_dispatch_recipient_communication') . '/adhocs';

    // Create the members object and populate member attributes.
    $members = (object) [
      'members' => [
        (object) [
          'toName' => $hr_data['FIRST_NAME'],
          'toAddress' => $recipient_email,
        ],
      ],
      'includeBatchResponse' => FALSE,
    ];
    // Generate additional member attributes for each webform component.
    // We assume submission data is single valued.
    foreach ($placeholders as $cid => $key) {
      $members->members[0]->$key = Xss::filter($form_state[$cid][0]);
    }

    // Post to Dispatch api to send the email.
    try {
      $response = $this->httpClient->get($endpoint, [
        'headers' => [
          'x-dispatch-api-key' => $apikey,
          'accept' => 'application/json',
        ],
        'method' => 'POST',
        'data' => $this->jsonController->encode($members),
      ]);
    }
    catch (RequestException $e) {
      $this->logger('uiowa_thankyou')
        ->warning($this->t('Dispatch request sent to: <em>@endpoint</em> and failed.', [
          '@endpoint' => $endpoint,
        ]));
    }
    if (!isset($response)) {
      return;
    }
    // Log the transaction for the system.
    $this->logger('uiowa_thankyou')
      ->notice($this->t('Dispatch request sent to: <em>@endpoint</em> and returned code: <em>@code</em>', [
        '@endpoint' => $endpoint,
        '@code' => $response->code,
      ]));

    // Send supervisor email.
    $endpoint = $uiowa_thankyou_settings->get('uiowa_thankyou_dispatch_supervisor_communication') . '/adhocs';
    // Update member object to send to the supervisor.
    $members->members[0]->toName = $hr_data['SUPERVISORS'][0]['FIRST_NAME'];
    $members->members[0]->toAddress = $hr_data['SUPERVISORS'][0]['EMAIL'];
    // Post to Dispatch api to send the email.
    try {
      $response = $this->httpClient->get($endpoint, [
        'headers' => [
          'x-dispatch-api-key' => $apikey,
          'accept' => 'application/json',
        ],
        'method' => 'POST',
        'data' => $this->jsonController->encode($members),
      ]);
    }
    catch (RequestException $e) {
      $this->logger('uiowa_thankyou')
        ->warning($this->t('Dispatch request sent to: <em>@endpoint</em> and failed.', [
          '@endpoint' => $endpoint,
        ]));
    }
    if (!isset($response)) {
      return;
    }
    // Log the transaction for the system.
    $this->logger('uiowa_thankyou')
      ->notice($this->t('Dispatch request sent to: <em>@endpoint</em> and returned code: <em>@code</em>', [
        '@endpoint' => $endpoint,
        '@code' => $response->code,
      ]));
  }

}
