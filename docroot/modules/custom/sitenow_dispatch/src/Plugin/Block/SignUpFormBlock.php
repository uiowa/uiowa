<?php

namespace Drupal\sitenow_dispatch\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "sitenow_dispatch_sign_up_form",
 *   admin_label = @Translation("Sign up form"),
 *   category = @Translation("SiteNow Dispatch")
 * )
 */
class SignUpFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config factory service.
   *
   * @var Dispatch
   */
  protected $dispatch;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory, FormBuilderInterface $formBuilder, $dispatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $configFactory;
    $this->formBuilder = $formBuilder;
    $this->dispatch = $dispatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('form_builder'),
      $container->get('sitenow_dispatch.dispatch')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // The returned populations.
    $populations = $this->dispatch->getFromDispatch("https://apps.its.uiowa.edu/dispatch/api/v1/populations");

    // If the population is empty, we have an invalid API key.
    if ($populations == []) {
      $form['invalid_api_key'] = [
        '#prefix' => '<div>',
        '#suffix' => '</div>',
        '#markup' => $this->t('Either there is no API key for SiteNow Dispatch currently configured, or the one you currently have is not valid. Please double check the API key set in the <a href=":url">Dispatch configuration</a>.', [
          ':url' => Url::fromRoute('sitenow_dispatch.settings_form')->toString(),
        ]),
      ];

      return $form;
    }

    $populationOptions = [];
    foreach ($populations as $population) {
      $response = $this->dispatch->getFromDispatch($population);
      if ($response->dataSourceType == "SubscriptionList") {
        $populationOptions[$response->id] = $response->name;
      }
    }

    $form['population'] = [
      '#type' => 'select',
      '#title' => $this->t('Population'),
      '#description' => $this->t('Select a population to use.'),
      '#default_value' => $this->configuration['population'] ?? '',
      '#required' => FALSE,
      '#options' => $populationOptions,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = $this->formBuilder->getForm('Drupal\sitenow_dispatch\Form\SubscribeForm', $this->configuration['population']);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['population'] = $form_state->getValue('population');
    parent::blockSubmit($form, $form_state);
  }

}
