<?php

namespace Drupal\registrar_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form for correspondence block.
 */
class CorrespondenceForm extends FormBase {

  /**
   * Constructs the SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\sitenow_dispatch\DispatchApiClientInterface $dispatch
   *   The Dispatch API client service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, protected DispatchApiClientInterface $dispatch, protected Connection $connection, protected RequestStack $request_stack) {
    $this->configFactory = $config_factory;
    $this->dispatch = $dispatch;
    $this->connection = $connection;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sitenow_dispatch.dispatch_client'),
      $container->get('database'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'correspondence_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    $wrapper_id = $this->getFormId() . '-wrapper';
    $form['#prefix'] = '<div id="' . $wrapper_id . '" aria-live="polite">';
    $form['#suffix'] = '</div>';

    $form['#id'] = 'correspondence-form';

    // Check if we have a form value set for the audience
    // and if not, check if we have a valid query param in the URL.
    $params = $this->requestStack->getCurrentRequest()->query;
    if ($form_state->getValue('audience')) {
      $audience = $form_state->getValue('audience');
    }
    elseif ($params->has('audience')) {
      $audience = $params->get('audience');
      // If the given audience param doesn't match our available options,
      // default to ALL.
      if (!in_array($audience, ['all', 'student', 'faculty_staff'])) {
        $audience = 'all';
      }
    }
    else {
      $audience = 'all';
    }
    if ($form_state->getValue('topic')) {
      $topic = $form_state->getValue('topic');
    }
    elseif ($params->has('topic')) {
      $topic = $params->get('topic');
      // If the given audience param doesn't match our available options,
      // default to ALL.
      if (!in_array($audience, array_keys(registrar_core_correspondence_tags()))) {
        $topic = '';
      }
    }
    else {
      $topic = '';
    }

    $mapping = [
      'all' => '',
      'student' => 'student',
      'faculty_staff' => 'faculty/staff',
    ];
    $rows = [];

    $data = $this->connection
      ->select('correspondence_archives', 'c')
      ->fields('c')
      ->condition('audience', '%' . $mapping[$audience] . '%', 'LIKE')
      ->condition('tags', '%' . $topic . '%', 'LIKE')
      ->orderBy('timestamp', 'DESC')
      ->execute();
    foreach ($data as $row) {
      $topics = [];
      foreach (registrar_core_correspondence_tags() as $tag => $display) {
        if (str_contains($row->tags, $tag)) {
          $topics[] = $display;
        }
      }
      $rows[] = [
        date('m/d/Y', $row->timestamp),
        $row->from_name,
        Link::fromTextAndUrl($row->subject, Url::fromUri($row->url)),
        $row->audience,
        implode(', ', $topics),
      ];
    }

    $headers = [
      'Created',
      'From',
      'Email',
      'Audience',
      'Topic',
    ];

    $form['audience'] = [
      '#type' => 'select',
      '#title' => $this->t('Audience'),
      '#description' => $this->t('The intended audience for the communications.'),
      '#default_value' => $audience,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'registrar-core-correspondence-table',
        'effect' => 'fade',
      ],
      '#options' => [
        'all' => $this->t('All'),
        'student' => $this->t('Student'),
        'faculty_staff' => $this->t('Faculty/Staff'),
      ],
    ];

    $form['topic'] = [
      '#type' => 'select',
      '#title' => $this->t('Topic'),
      '#description' => $this->t('The communication topic.'),
      '#empty_option' => '- Select -',
      '#default_value' => $topic,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'registrar-core-correspondence-table',
        'effect' => 'fade',
      ],
      '#options' => registrar_core_correspondence_tags(),
    ];

    $form['table'] = [
      '#theme' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#attributes' => [
        'id' => 'registrar-core-correspondence-table',
      ],
    ];

    return $form;

  }

  /**
   * AJAX callback for the form.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['table'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No-op.
  }

}
