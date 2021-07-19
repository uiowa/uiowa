<?php

namespace Drupal\sitenow_dispatch\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\sitenow_media_wysiwyg\Plugin\media\Source\Panopto;
use GuzzleHttp\ClientInterface;
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
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config factory service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = \Drupal::formBuilder()->getForm('Drupal\sitenow_dispatch\Form\SubscribeForm', $this->configuration['population']);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('config.factory'), $container->get('http_client'));
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory, ClientInterface $client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $configFactory;
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // The returned populations.
    $populations = $this->getFromDispatch("https://apps.its.uiowa.edu/dispatch/api/v1/populations");

    $populationOptions = [];
    foreach ($populations as $population) {
      $response = $this->getFromDispatch($population);
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
   * Helper function for doing get commands from dispatch.
   */
  public function getFromDispatch(string $request) {
    try {
      $response = $this->client->request('GET', $request, [
        'headers' => [
          'Accept' => 'application/json',
          'x-dispatch-api-key' => $this->configFactory->get('sitenow_dispatch.settings')->get('API_key'),
        ]
      ]);
    }
    catch (RequestException | GuzzleException $e) {
      $this->logger->error($e->getMessage());
    }

    return json_decode($response->getBody()->getContents());
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['population'] = $form_state->getValue('population');
    parent::blockSubmit($form, $form_state);
  }

}
