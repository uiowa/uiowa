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
   * The config factory service.
   *
   * @var Dispatch
   */
  protected $dispatch;

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
  public function __construct(ConfigFactoryInterface $config_factory, $dispatch) {
    parent::__construct($config_factory);
    $this->dispatch = $dispatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sitenow_dispatch.dispatch')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description_text'] = [
      '#markup' => '<p><a href="https://its.uiowa.edu/dispatch">Dispatch</a> is a web service that allows users to create and manage campaigns to generate PDF content, email messages, SMS messages, or voice calls. You must have a Dispatch <a href="https://apps.its.uiowa.edu/dispatch/help/faq#q1">client and account</a> to use Dispatch functionality within your site.</p>',
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $this->config('sitenow_dispatch.settings')->get('api_key'),
      '#description' => $this->t('A valid Dispatch client API key. See the Dispatch <a href="https://apps.its.uiowa.edu/dispatch/help/api">API key documentation</a> for more information.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('sitenow_dispatch.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $response = $this->dispatch->getFromDispatch('https://apps.its.uiowa.edu/dispatch/api/v1/populations', $form_state->getValue('api_key'));

    // If the response is empty, we have an invalid API key.
    if ($response == []) {
      $form_state->setErrorByName('api_key', 'Invalid API key, please verify that your API key is correct and try again.');
    }

    parent::validateForm($form, $form_state);
  }

}
