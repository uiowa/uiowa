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
   * @var \Drupal\sitenow_dispatch\Dispatch
   */
  protected $dispatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
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
    // Set the form tree so we can access all the placeholders easily later.
    $form['#tree'] = TRUE;

    $form['to_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address of employee you want to thank'),
      '#description' => $this->t('An <em>@uiowa.edu</em> email address. Look up an email address in our <a target="_blank" href="https://iam.uiowa.edu/whitepages/search">directory</a>.'),
      '#required' => TRUE,
      '#pattern' => '.+@uiowa\.edu',
    ];

    $form['placeholder']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
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

    if ($this->config('sitenow_dispatch.settings')->get('thanks.supervisor')) {
      $form['placeholder']['message']['#description'] = $this->t("<div><em>A copy of this message will be sent to the employee's supervisor(s).</em></div>");
    }

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
    $env = getenv('AH_SITE_ENVIRONMENT') ?: 'local';
    $email = $form_state->getValue('to_email');
    $endpoint = ($env == 'prod' || $env == 'test') ? 'https://data.its.uiowa.edu/hris/supervisors' : 'https://data-test.its.uiowa.edu/hris/supervisors';
    $config = $this->config('sitenow_dispatch.settings');

    // Get HR data.
    $token = $config->get('thanks.hr_token');

    try {
      $request = $this->httpClient->get("$endpoint/$email?api_token=$token", [
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

      // Output a specific 404 error message, otherwise a generic one.
      if ($e->getCode() == 404) {
        $form_state->setError($form, $this->t('Could not find any university record for email @email. Double check the email address and try again.', [
          '@email' => $email,
        ]));
      }
      else {
        $form_state->setError($form, $this->t('An error was encountered processing the form. If the problem persists, please contact the <a href=":link">ITS Help Desk</a>.', [
          ':link' => 'https://its.uiowa.edu/contact',
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $hr_data = $form_state->getValue('hr_data');
    $config = $this->config('sitenow_dispatch.settings');
    $api_key = $config->get('thanks.api_key');
    $communication = $config->get('thanks.communication');

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
          'footer_statement' => $this->t('This email was sent from @from to @to using the form at <a href=":request">@host</a>.', [
            '@from' => $form_state->getValue(['placeholder', 'from_email']),
            '@to' => $form_state->getValue('to_email'),
            ':request' => $this->getRequest()->getUri(),
            '@host' => $this->getRequest()->getHost(),
          ]),
        ],
      ],
      'includeBatchResponse' => FALSE,
    ];

    // Add the placeholders to the recipient (first) member.
    foreach ($placeholders as $key => $value) {
      $data['members'][0][$key] = Xss::filter($value);
    }

    // Create a copy of the first member as we'll use this data for others.
    $recipient = $data['members'][0];

    // If we're configured to include supervisor emails, modify the recipient
    // (first) member to denote this in the footer statement and then add the
    // supervisor(s) to our member data. Otherwise, set it to an empty string.
    if ($config->get('thanks.supervisor')) {
      // Duplicate recipient member data but change toName/Address and subject.
      foreach ($hr_data['supervisors'] as $supervisor) {
        $data['members'][] = array_merge($recipient, [
          'toName' => $supervisor['first_name'] . ' ' . $supervisor['last_name'],
          'toAddress' => $supervisor['email'],
          'subject' => $title . ' (Supervisor Copy)',
        ]);
      }

      // Modify the footer statement for the recipient.
      $data['members'][0]['footer_statement'] .= ' A copy of it has been sent to your supervisor(s).';
    }

    // Add additional email as member if it is configured with modified data.
    if ($email = $config->get('thanks.email')) {
      $data['members'][] = array_merge($recipient, [
        'toName' => NULL,
        'toAddress' => $email,
        'subject' => $title . ' (Copy)',
      ]);
    }

    // Prepare the data for the request body JSON.
    $data = (object) $data;

    foreach ($data->members as $key => $member) {
      $data->members[$key] = (object) $member;
    }

    // Attempt to post to Dispatch API to send the emails, and let the user
    // know if it was successful or if an error occurred. The actual error will
    // be logged by the dispatch service.
    $posted = $this->dispatch->request('POST', "communications/$communication/adhocs", [], [
      'body' => json_encode($data),
      'headers' => [
        'x-dispatch-api-key' => $api_key,
      ],
    ]);
    if ($posted === FALSE) {
      $this->messenger()->addError($this->t('An error was encountered processing the form. If the problem persists, please contact the <a href=":link">ITS Help Desk</a>.', [
        ':link' => 'https://its.uiowa.edu/contact',
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The form has been submitted successfully.'));
    }
  }

}
