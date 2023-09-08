<?php

namespace Drupal\facilities_core\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
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

    if (is_null($this->dispatch->getKey())) {
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

    $form['title'] = [
      '#markup' => $this->t('<h3>Schedule Email Communication</h3>'),
    ];

    $form['start'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Send date and time'),
      '#required' => TRUE,
      '#date_increment' => 60,
    ];

    $form['placeholders'] = [
      '#type' => 'details',
      '#title' => $this->t('Placeholders'),
      '#description' => $this->t('These placeholders will be used to fill in the message that is sent.'),
      '#open' => FALSE,
    ];

    $placeholders = $this->getPlaceholders();

    foreach ($placeholders as $placeholder => $preview) {

      $form['placeholders'][$placeholder]['label'] = [
        '#type' => 'label',
        '#title' => $placeholder,
      ];

      if (!empty($preview)) {
        $form['placeholders'][$placeholder]['value'] = [
          '#markup' => $preview,
        ];
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Dispatch request'),
    ];


    // Only render table data if there's field data.
    if (!empty($node->field_dispatch_log)) {
      $form['log_fieldset'] = [
        '#type' => 'details',
        '#title' => $this->t('Dispatch Log'),
      ];
      // Build table header.
      $header = [
        [
          'data' => t('Date Requested'),
          'field' => 'timestamp',
          'sort' => 'desc',
        ],
        [
          'data' => t('User'),
          'field' => 'username',
        ],
        [
          'data' => t('Communication ID'),
          'field' => 'message_id',
        ],
      ];
      // Build table rows.
      $rows = [];
      foreach ($node->field_dispatch_log as $delta => $log) {
        $row = unserialize($log->value);
        foreach ($row as $key => &$d) {
          switch ($key) {
            case 'timestamp':
              $d = [
                '#markup' => new FormattableMarkup('<span class="sr-only">@timestamp</span>@date', [
                  '@timestamp' => $d,
                  '@date' => \Drupal::service('date.formatter')->format($d, 'custom', 'M j, Y - g:i:sa'),
                ]),
              ];
              break;

            case 'message_id':
//              $url = Url::fromUri('https://apps.its.uiowa.edu/dispatch/messages/view/' . $d);
//              $d = (Link::fromTextAndUrl($d, $url))->toRenderable();
//              $d['#attributes'] = ['target' => '_blank'];
              break;
          }
        }
        $rows[] = $row;
      }
      // Sort table data.
      //      $order = tablesort_get_order($header);
      //      $sort = tablesort_get_sort($header);
      //      $sql = $order['sql'];
      //      if ($sort == 'asc') {
      //        usort($rows, function($a, $b) use ($sql) {
      //          return ($a[$sql] < $b[$sql]) ? -1 : 1;
      //        });
      //      }
      //      if ($sort == 'desc') {
      //        usort($rows, function($a, $b) use ($sql) {
      //          return ($a[$sql] > $b[$sql]) ? -1 : 1;
      //        });
      //      }
      // Render table results.
      $form['log_fieldset']['results_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('facilities_core.settings');
    $schedule_start = strtotime($form_state->getValue('start'));
    $schedule_start = date('M d, Y H:i:s', $schedule_start);

    $communication_id = $config->get('alert_dispatch_communication_id');

    $placeholders = $this->getPlaceholders();

    $result_endpoint = $this->dispatch->postCommunicationSchedule($communication_id, $schedule_start, $placeholders);

    $message = $this->dispatch->request('GET', $result_endpoint);

    // @todo Finish setting up the log.
    $this->node->field_dispatch_log[] = serialize([
      'timestamp' => $schedule_start,
      'username' => \Drupal::currentUser()->getAccountName(),
      'message_id' => $message->id,
    ]);

    $this->node->save();
    $this->messenger()->addMessage($this->t('Message request has been sent.'));
  }

  /**
   * Prepare placeholders for display.
   *
   * @return array
   *   The rendered placeholder values keyed by placeholder name.
   */
  protected function getPlaceholders() {
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

    return $placeholders;
  }

}
