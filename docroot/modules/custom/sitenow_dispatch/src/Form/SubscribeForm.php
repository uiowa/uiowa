<?php

namespace Drupal\sitenow_dispatch\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure sitenow_dispatch settings for this site.
 */
class SubscribeForm extends ConfigFormBase {
  /**
   * The config factory service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static($container->get('config.factory'), $container->get('http_client'));
  }
  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, $client)
  {
    parent::__construct($config_factory);
    $this->client = $client;
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
  public function buildForm(array $form, FormStateInterface $form_state, $population = null) {
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
    try {
      $response = $this->client->request('POST', 'https://apps.its.uiowa.edu/dispatch/api/v1/populations/' . $form_state->getValue('population') . '/subscribers', [
        'headers' => [
          'Accept' => 'application/json',
          'x-dispatch-api-key' => $this->configFactory->get('sitenow_dispatch.settings')->get('API_key'),
        ],
        'body' => json_encode([
            "toAddress" => $form_state->getValue('email'),
            "firstName" => $form_state->getValue('first'),
            "lastName" => $form_state->getValue('last'),
        ])
      ]);
    }
    catch (RequestException | GuzzleException $e) {
      $this->logger->error($e->getMessage());
    }
    $this->messenger()->addStatus($this->t("You've been Aardvarked!"));
    parent::submitForm($form, $form_state);
  }
}
