<?php

namespace Drupal\facilities_core\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
use Drupal\sitenow_dispatch\MessageLogRepository;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Send Dispatch requests for alert nodes.
 */
class NodeAlertDispatchForm extends FormBase {

  /**
   * Message Log repository service.
   *
   * @var \Drupal\sitenow_dispatch\MessageLogRepository
   */
  protected MessageLogRepository $repository;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

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
   * @param \Drupal\sitenow_dispatch\MessageLogRepository $repository
   *   The message repository.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   */
  public function __construct(protected DispatchApiClientInterface $dispatch, protected RendererInterface $renderer, MessageLogRepository $repository, AccountProxyInterface $current_user, UserStorageInterface $user_storage) {
    $this->repository = $repository;
    $this->currentUser = $current_user;
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sitenow_dispatch.dispatch_client'),
      $container->get('renderer'),
      $container->get('sitenow_dispatch.message_log_repository'),
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('user')
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

    // Load log messages related to this node.
    $logs = $this->repository->load(['entity_id' => $node->id()]);

    if (!empty($logs)) {
      $logs = json_decode(json_encode($logs), TRUE);
      $form['notification_log'] = [
        '#type' => 'details',
        '#title' => $this->t('Dispatch Log'),
        '#open' => TRUE,
      ];
      $header = [
        [
          'data' => $this->t('Message ID'),
          'field' => 'mid',
        ],
        [
          'data' => $this->t('User'),
          'field' => 'uid',
        ],
        [
          'data' => $this->t('Date Requested'),
          'field' => 'date',
          'sort' => 'desc',
        ],
      ];
      // Build table rows.
      $rows = [];
      foreach ($logs as $row) {

        foreach ($row as $key => &$d) {
          unset($row['lid']);
          unset($row['entity_id']);
          switch ($key) {
            case 'date':
              $d = new FormattableMarkup('<span class="sr-only">@timestamp</span>@date', [
                '@timestamp' => $d,
                '@date' => \Drupal::service('date.formatter')->format(strtotime($d), 'custom', 'M j, Y - g:i:sa'),
              ]);
              break;

            case 'uid':
              $account = $this->userStorage->load($d);
              $d = $account->getAccountName();
              break;

            case 'mid':
              $communication_id_slug = basename($communication_id);
              // https://apps.its.uiowa.edu/dispatch/communications/1238605612?showBatch=1241267773
              $url = Url::fromUri("https://apps.its.uiowa.edu/dispatch/communications/$communication_id_slug?showBatch=$d");
              $d = Link::fromTextAndUrl($d, $url)->toString();
              break;
          }
        }
        $rows[] = $row;
      }

      // Render table results.
      $form['notification_log']['table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Dispatch request'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('facilities_core.settings');
    $schedule_start = strtotime($form_state->getValue('start'));

    $communication_id = $config->get('alert_dispatch_communication_id');

    $placeholders = $this->getPlaceholders();

    $result_endpoint = $this->dispatch->postCommunicationSchedule($communication_id, $schedule_start, $placeholders);

    $message = $this->dispatch->request('GET', $result_endpoint);

    // Create log entry.
    $entry = [
      'mid' => $message->id,
      'date' => date('Y-m-d H:i:s', $schedule_start),
      'entity_id' => $this->node->id(),
      'uid' => $this->currentUser->id(),
    ];
    $this->repository->insert($entry);

    $this->messenger()->addMessage($this->t('Message request has been sent.'));
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['dispatch:message']);
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
          $placeholders[$placeholder] = $this->node->getTitle();
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
