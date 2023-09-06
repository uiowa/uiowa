<?php

namespace Drupal\facilities_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Send Dispatch requests for alert nodes.
 */
class NodeAlertDispatchForm extends FormBase {

  /**
   * The node being acted upon.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * Constructor method for NodeAlertDispatchForm class.
   *
   * @param \Drupal\sitenow_dispatch\DispatchApiClientInterface $dispatch
   *   The Dispatch API client.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(protected DispatchApiClientInterface $dispatch, protected RendererInterface $renderer) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sitenow_dispatch.dispatch_client'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_alert_dispatch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $config = $this->config('facilities_core.settings');
    $this->node = $node;

    if (is_null($this->dispatch->getApiKey())) {
      $form['no_api_key'] = [
        '#markup' => $this->t('A Dispatch API key has not been entered. Please add your API key.'),
      ];

      return $form;
    }

    $communication_id = $config->get('alert_dispatch_communication_id');

    if (!$communication_id) {
      $form['no_communication_id'] = [
        '#markup' => $this->t('A Dispatch communication ID has not been entered. Please select a communication ID in settings.'),
      ];

      return $form;
    }

    $form['schedule'] = [
      '#type' => 'details',
      '#title' => $this->t('Schedule Email Communication'),
      '#open' => TRUE,
      '#collapsible' => FALSE,
    ];

    $form['schedule']['start'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Send date and time'),
      '#required' => TRUE,
      '#date_increment' => 60,
    ];

    $form['schedule']['placeholder_label'] = [
      '#markup' => '<h3>Placeholders</h3>',
    ];

    $placeholders = _sitenow_dispatch_get_placeholders('alert');

    foreach ($placeholders as $field_name => $placeholder) {
      $value = $node->{$field_name}?->view('dispatch') ?? [];

      $form['schedule'][$field_name]['label'] = [
        '#type' => 'label',
        '#title' => $placeholder,
      ];

      $form['schedule'][$field_name]['value'] = $value;
    }

    $form['schedule']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send DispatchApiClient request'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $config = $this->config('facilities_core.settings');
    $schedule_start = strtotime($form_state->getValue('start'));

    $communication_id = $config->get('alert_dispatch_communication_id');

    $placeholders = [];

    foreach (_sitenow_dispatch_get_placeholders('alert') as $field_name => $placeholder) {
      switch ($field_name) {
        case 'alert_subject':
          $placeholders[$placeholder] = $this->node->getTitle() . ' - OSC TEST';
          break;

        default:
          $render = $this->node->{$field_name}?->view('dispatch');
          if (!empty($render)) {
            $placeholders[$placeholder] = $this->renderer->renderRoot($render);
          }
      }
    }

    $data = (object) [
      'occurrence' => 'ONE_TIME',
      'startTime' => date('Y-m-d H:i:s', $schedule_start),
      'businessDaysOnly' => FALSE,
      'includeBatchResponse' => TRUE,
      'createPublicArchive' => FALSE,
      'communicationOverrideVars' => (object) $placeholders,
    ];

    $this->dispatch->request('POST', $communication_id . '/schedules', [], [
      'json' => $data,
    ]);
  }

}
