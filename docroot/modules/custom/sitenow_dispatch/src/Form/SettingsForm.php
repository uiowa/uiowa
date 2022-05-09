<?php

namespace Drupal\sitenow_dispatch\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure sitenow_dispatch settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The dispatch service.
   *
   * @var \Drupal\sitenow_dispatch\Dispatch
   */
  protected $dispatch;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_dispatch_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sitenow_dispatch.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, $dispatch, $entityTypeManager) {
    parent::__construct($config_factory);
    $this->dispatch = $dispatch;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sitenow_dispatch.dispatch'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->get('sitenow_dispatch.settings');
    $api_key = $config->get('api_key');

    $form['description_text'] = [
      '#markup' => '<p><a href="https://its.uiowa.edu/dispatch">Dispatch</a> is a web service that allows users to create and manage campaigns to generate PDF content, email messages, SMS messages, or voice calls. You must have a Dispatch <a href="https://apps.its.uiowa.edu/dispatch/help/faq#q1">client and account</a> to use certain Dispatch functionality within your site.</p>',
    ];

    $form['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('API key'),
      '#attributes' => [
        'value' => $api_key,
      ],
      '#description' => $this->t('A valid Dispatch client API key. See the Dispatch <a href="https://apps.its.uiowa.edu/dispatch/help/api">API key documentation</a> for more information.'),
      '#required' => TRUE,
    ];

    if ($api_key && $client = $config->get('client')) {
      $form['api_key']['#description'] .= $this->t('&nbsp;<em>Currently set to @client client</em>.', [
        '@client' => $client,
      ]);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $api_key = trim($form_state->getValue('api_key'));

    // Validate the api_key being submitted in the form rather than config.
    $client = $this->dispatch->request('GET', 'client', [], [
      'headers' => [
        'x-dispatch-api-key' => $api_key,
      ],
    ]);

    if ($client === FALSE) {
      $form_state->setErrorByName('api_key', 'Invalid API key, please verify that your API key is correct and try again.');
    }
    else {
      $form_state->setValue('api_key', $api_key);
      $form_state->setValue('client', $client->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('sitenow_dispatch.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('client', $form_state->getValue('client'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
