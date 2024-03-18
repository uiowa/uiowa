<?php

namespace Drupal\facilities_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * Constructs the SettingsForm object.
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
  protected function getEditableConfigNames() {
    return ['facilities_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facilities_core_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('facilities_core.settings');

    if (is_null($this->dispatch->getKey())) {
      $form['no_api_key'] = [
        '#markup' => $this->t('A Dispatch API key has not been entered. Please add your API key.')
      ];

      return $form;
    }

    $form['campaign'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'dispatch-campaign'
      ],
    ];

    $campaigns = $this->dispatch->getCampaigns();
    array_unshift($campaigns, 'None');

    $default_campaign = $config->get('alert_dispatch_campaign_id');

    $form['campaign']['campaign_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Campaign'),
      '#description' => $this->t('Choose a Dispatch campaign.'),
      '#default_value' => $default_campaign,
      '#options' => $campaigns,
      '#ajax' => [
        'callback' => [$this, 'campaignSelected'],
        'event' => 'change',
        'wrapper' => 'dispatch-campaign',
      ],
    ];

    $communications = $this->dispatch->getCommunications($default_campaign);
    array_unshift($communications, 'None');
    $form['campaign']['communication_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Communication'),
      '#description' => $this->t('Choose the Dispatch communication to use.'),
      '#default_value' => $config->get('alert_dispatch_communication_id'),
      '#options' => $communications,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('facilities_core.settings');
    $config->set('alert_dispatch_campaign_id', $form_state->getValue('campaign_id'));
    $config->set('alert_dispatch_communication_id', $form_state->getValue('communication_id'));
    $config->save();
  }

  /**
   * AJAX callback to populate communications when a campaign has been selected.
   */
  public function campaignSelected(array &$form, FormStateInterface $form_state) {
    if ($campaign = $form_state->getValue('campaign')) {
      if ($campaign !== 0) {
        $communications = $this->dispatch->getCommunications($campaign);
        array_unshift($communications, 'None');
        $form['campaign']['communication'] = [
          '#type' => 'select',
          '#title' => $this->t('Communication'),
          '#description' => $this->t('Choose the Dispatch communication to use.'),
          '#options' => $communications,
        ];
      }
    }

    return $form['campaign'];
  }

}
