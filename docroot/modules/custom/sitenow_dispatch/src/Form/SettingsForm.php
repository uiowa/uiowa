<?php

namespace Drupal\sitenow_dispatch\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sitenow_dispatch\DispatchApiClient;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure sitenow_dispatch settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The dispatch service.
   */
  protected DispatchApiClient $dispatch;

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
  public function __construct(ConfigFactoryInterface $config_factory, DispatchApiClientInterface $dispatch, $entityTypeManager) {
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
      $container->get('sitenow_dispatch.dispatch_client'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sitenow_dispatch.settings');
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
    ];

    if ($api_key) {
      if ($client = $config->get('client')) {
        $form['api_key']['#description'] .= $this->t('&nbsp;<em>Currently set to @client client</em>.', [
          '@client' => $client,
        ]);
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $api_key = trim($form_state->getValue('api_key'));

    if ($api_key) {
      // Validate the api_key being submitted in the form rather than config.
      $client = $this->dispatch->setKey($api_key)->get('client');

      if ($client === FALSE) {
        $form_state->setErrorByName('api_key', 'Invalid API key, please verify that your API key is correct and try again.');
      }
      else {
        $form_state->setValue('api_key', $api_key);
        $form_state->setValue('client', $client->name);
      }
    }
    else {
      $form_state->unsetValue('api_key');
      $form_state->unsetValue('client');
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
