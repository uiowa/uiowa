<?php

namespace Drupal\sitenow_dispatch\Form;

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
   * The config factory service.
   *
   * @var Dispatch
   */
  protected $dispatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->configStorage = $container->get('config.storage');
    $instance->jsonController = $container->get('serialization.json');
    $instance->httpClient = $container->get('http_client');
    $instance->dispatch = $container->get('sitenow_dispatch.dispatch');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_dispatch_thankyou_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['to_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address of employee you want to thank'),
      '#description' => $this->t('Look up an email address in our <a target="_blank" href="https://iam.uiowa.edu/whitepages/search">directory</a>.'),
      '#required' => TRUE,
    ];

    $form['placeholder']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
    ];

    $form['placeholder']['from_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Nominator's Name"),
      '#required' => TRUE,
    ];

    $form['placeholder']['from_email'] = [
      '#type' => 'email',
      '#title' => $this->t("Nominator's Email Address"),
      '#required' => TRUE,
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
    $config = $this->config('sitenow_dispatch.settings');

    // Get HR data.
    $token = $config->get('thanks.hr_token');
    $endpoint = $config->get('thanks.hr_endpoint') . $form_state->getValue('to_email') . "?api_token=$token";

    try {
      $request = $this->httpClient->get($endpoint, [
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);

      $hr_data = $this->jsonController->decode($request->getBody()->getContents());
      $form_state->setValue('hr_data', $hr_data);
      parent::validateForm($form, $form_state);
    }
    catch (RequestException | GuzzleException | ClientException $e) {
      $this->logger('sitenow_dispatch')->error($this->t('HR API error: @error.', [
        '@error' => $e->getMessage(),
      ]));

      $form_state->setError($form, $this->t('An error was encountered processing the form. If the problem persists, please contact the ITS Help Desk.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $hr_data = $form_state->getValue('hr_data');
    $config = $this->config('sitenow_dispatch.settings');
    $apikey = trim($config->get('thanks.api_key'));
    $endpoint = 'https://apps.its.uiowa.edu/dispatch/api/v1/communications/' . $config->get('thanks.communication') . '/adhocs';

    // Combine placeholders on thank you form with settings form.
    $title = $config->get('thanks.placeholder.title');
    $placeholders = array_merge($form_state->getValue('placeholder'), $config->get('thanks.placeholder'));

    // Dispatch API data.
    $data = [
      'members' => [
        [
          'toName' => $hr_data['first_name'] . ' ' . $hr_data['last_name'],
          'toAddress' => $form_state->getValue('to_email'),
          'subject' => $title,
        ],
      ],
      'includeBatchResponse' => FALSE,
    ];

    // Add the placeholders to the recipient (first) member.
    foreach ($placeholders as $key => $value) {
      $data['members'][0][$key] = Xss::filter($value);
    }

    // Duplicate first member to get placeholders but change to CC supervisor.
    // @todo Make supervisor CC optional.
    foreach ($hr_data['supervisors'] as $supervisor) {
      $data['members'][] = array_merge($data['members'][0], [
        'toName' => $supervisor['first_name'] . ' ' . $supervisor['last_name'],
        'toAddress' => $supervisor['email'],
        'subject' => $title . ' (Supervisor Copy)',
      ]);
    }

    // Prepare the data for the request body JSON.
    $data = (object) $data;

    foreach ($data->members as $key => $member) {
      $data->members[$key] = (object) $member;
    }

    // Attempt to post to Dispatch API to send the emails,
    // and let the user know if it was successful
    // or if an error occurred (the actual error will be logged by the
    // dispatch service.
    $posted = $this->dispatch->postToDispatch($this->jsonController->encode($data), $endpoint, $apikey);
    if ($posted) {
      $this->messenger()->addMessage($this->t('The form has been submitted successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('An error was encountered processing the form. If the problem persists, please contact the ITS Help Desk.'));
    }
  }

}
