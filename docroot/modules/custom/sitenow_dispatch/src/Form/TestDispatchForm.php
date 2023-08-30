<?php

namespace Drupal\sitenow_dispatch\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
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
   * @var \Drupal\sitenow_dispatch\Dispatch
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
      $container->get('sitenow_dispatch.dispatch'),
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
        '#markup' => $this->t('A Dispatch API key has not been entered. Please add your API key.')
      ];

      return $form;
    }

    $campaigns = $this->dispatch->getCampaigns();
    array_unshift($campaigns, 'None');

    $form['campaign'] = [
      '#type' => 'select',
      '#title' => $this->t('Campaign'),
      '#description' => $this->t('Choose a Dispatch campaign.'),
      '#default_value' => 'https://apps.its.uiowa.edu/dispatch/api/v1/campaigns/1233665067',
      '#options' => $campaigns,
    ];

    $populations = $this->dispatch->getPopulations();
    array_unshift($populations, 'None');

    $form['population'] = [
      '#type' => 'select',
      '#title' => $this->t('Population'),
      '#description' => $this->t('Choose a Dispatch population.'),
      '#default_value' => 'https://apps.its.uiowa.edu/dispatch/api/v1/populations/612115495',
      '#options' => $populations,
    ];

    $suppression_list = $this->dispatch->getSuppressionLists();
    array_unshift($suppression_list, 'None');

    $form['suppression_list'] = [
      '#type' => 'select',
      '#title' => $this->t('Suppression list'),
      '#description' => $this->t('Choose a Dispatch suppression list.'),
      '#default_value' => '',
      '#options' => $suppression_list,
    ];

    $templates = $this->dispatch->getTemplates();
    array_unshift($templates, 'None');

    $form['template'] = [
      '#type' => 'select',
      '#title' => $this->t('Template'),
      '#description' => $this->t('Choose a Dispatch template.'),
      '#default_value' => 'https://apps.its.uiowa.edu/dispatch/api/v1/templates/1237284721',
      '#options' => $templates,
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
    $campaign = $form_state->getValue('campaign');
    $subject = $form_state->getValue('subject');
    $data = (object) [
      'type' => 'EMAIL',
      'name' => date('Y-m-d H:i:s',time()) . ' Dispatch Test',
      'email' => (object) [
        'fromAddress' => 'its-web@uiowa.edu',
        'fromName' => 'OSC - Web Services',
        'subject' => $subject,
      ],
      'destinations' => (object) [
        (object) [
          'bounceAddress' => 'its-web@uiowa.edu',
          'linkTrackingDisabled' => 'false',
          'openTrackingDisabled' => 'false',
          'replyToAddress' => 'its-web@uiowa.edu',
          'type' => 'SMTP',
          'suppressionList' => $form_state->getValue('suppression_list'),
        ],
      ],
      'placeholders' => (object) [
        'Title' => $subject,
        'Body' => $form_state->getValue('body'),
      ],
      'priority' => '5',
      'bypassApproval' => 'true',
      'template' => $form_state->getValue('template'),
      'population' => $form_state->getValue('population'),
    ];

    $response = $this->dispatch->request('POST', $campaign . '/communications', [], [
      'data' => json_encode($data),
    ]);
  }

}
