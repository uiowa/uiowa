<?php

namespace Drupal\sitenow_dispatch\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure sitenow_dispatch settings for this site.
 */
class SubscribeForm extends ConfigFormBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The config factory service.
   *
   * @var Dispatch
   */
  protected $dispatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, $client, $dispatch) {
    parent::__construct($config_factory);
    $this->client = $client;
    $this->dispatch = $dispatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('http_client'), $container->get('sitenow_dispatch.dispatch'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_dispatch_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sitenow_dispatch.subscribe_form'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $population = NULL) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $this->config('sitenow_dispatch.subscribe_form')->get('email'),
    ];
    $form['first'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#default_value' => $this->config('sitenow_dispatch.subscribe_form')->get('first'),
    ];
    $form['last'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#default_value' => $this->config('sitenow_dispatch.subscribe_form')->get('last'),
    ];
    $form['population'] = [
      '#type' => 'hidden',
      '#value' => $population,
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

    $email      = $form_state->getValue('email');
    $first      = $form_state->getValue('first');
    $last       = $form_state->getValue('last');
    $api_key    = $this->configFactory->get('sitenow_dispatch.settings')->get('API_key');
    $population = $form_state->getValue('population');

    // This try block will add someone to the subscriber list.
    try {
      $response = $this->client->request('POST', 'https://apps.its.uiowa.edu/dispatch/api/v1/populations/' . $population . '/subscribers', [
        'headers' => [
          'Accept' => 'application/json',
          'x-dispatch-api-key' => $api_key,
        ],
        'body' => json_encode([
          "toAddress" => $email,
          "firstName" => $first,
          "lastName" => $last,
        ]),
      ]);
    }
    catch (RequestException | GuzzleException $e) {
      $this->logger->error($e->getMessage());
    }
    $this->messenger()->addStatus(
      $this->t(
        '"@first @last" has been added to the subscription list with the email "@email"',
        ['@first' => $first, '@last' => $last, '@email' => $email]
      )
    );
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email         = $form_state->getValue('email');
    $population    = $form_state->getValue('population');
    $encoded_email = UrlHelper::buildQuery(['search' => $email]);

    $response = $this->dispatch->getFromDispatch('https://apps.its.uiowa.edu/dispatch/api/v1/populations/' . $population . '/subscribers?' . $encoded_email);

    if ($response->recordsReturned > 0) {
      $form_state->setErrorByName('email', 'This email is already subscribed to the related subscription list.');
    }

    parent::validateForm($form, $form_state);
  }

}
