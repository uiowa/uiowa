<?php

namespace Drupal\sitenow_dispatch\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure sitenow_dispatch settings for this site.
 */
class TestDispatchForm extends FormBase {

  /**
   * The dispatch service.
   *
   * @var \Drupal\sitenow_dispatch\DispatchApiClient
   */
  protected $dispatch;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_dispatch_test_dispatch';
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
  public function __construct($dispatch) {
    $this->dispatch = $dispatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sitenow_dispatch.dispatch_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sitenow_dispatch.settings');
    $api_key = $config->get('api_key');

    if (!$api_key) {
      $form['no_api_key'] = [
        '#markup' => $this->t('A Dispatch API key has not been entered. Please add your API key.'),
      ];

      return $form;
    }

    $form['campaign'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'dispatch-campaign',
      ],
    ];

    $campaigns = $this->dispatch->getCampaigns();
    array_unshift($campaigns, 'None');

    $default_campaign = 'https://apps.its.uiowa.edu/dispatch/api/v1/campaigns/1233665067';

    $form['campaign']['campaign'] = [
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
    $form['campaign']['communication'] = [
      '#type' => 'select',
      '#title' => $this->t('Communication'),
      '#description' => $this->t('Choose the Dispatch communication to use.'),
      '#default_value' => '',
      '#options' => $communications,
    ];

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => 'Test subject',
    ];

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#default_value' => 'Test body',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test Dispatch'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo Add validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $subject = $form_state->getValue('subject');

    $communication = $form_state->getValue('communication');

    if ($communication) {

      $data = (object) [
        'occurrence' => 'ONE_TIME',
        'startTime' => date('Y-m-d H:i:s', time()),
        'businessDaysOnly' => FALSE,
        'includeBatchResponse' => TRUE,
        'createPublicArchive' => FALSE,
        'communicationOverrideVars' => (object) [
          'dynamicSubject' => $subject,
        ],
      ];

      $this->dispatch->request('POST', $communication . '/schedules', [], [
        'json' => $data,
      ]);
    }
  }

  /**
   * Callback function responding to when a compaign is selected.
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
