<?php

namespace Drupal\sitenow_dispatch\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure sitenow_dispatch settings for this site.
 */
class SubscribeForm extends ConfigFormBase {

  /**
   * Constructs the SubscribeForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\sitenow_dispatch\DispatchApiClientInterface $dispatch
   *   The Dispatch API client service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, protected DispatchApiClientInterface $dispatch) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sitenow_dispatch.dispatch_client'),
    );
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
      '#required' => TRUE,
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
    $email = $form_state->getValue('email');
    $first = $form_state->getValue('first');
    $last = $form_state->getValue('last');
    $population = $form_state->getValue('population');

    $this->dispatch->request('POST', "populations/$population/subscribers", [
      'body' => json_encode([
        'toAddress' => $email,
        'firstName' => $first,
        'lastName' => $last,
      ]),
    ]);

    $this->messenger()->addStatus($this->t('@email has been added to the subscription list.', [
      '@email' => $email,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $population = $form_state->getValue('population');

    $response = $this->dispatch->get("populations/$population/subscribers", [
      'query' => [
        'search' => $email,
      ],
    ]);

    if ($response->recordsReturned > 0) {
      $form_state->setErrorByName('email', 'This email is already subscribed to the related subscription list.');
    }

    parent::validateForm($form, $form_state);
  }

}
