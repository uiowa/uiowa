<?php

namespace Drupal\registrar_core\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *    The cache backend service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, protected DispatchApiClientInterface $dispatch, protected CacheBackendInterface $cache) {
    $this->configFactory = $config_factory;
    $this->dispatch = $dispatch;
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sitenow_dispatch.dispatch_client'),
      $container->get('cache.default'),
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

    $params = \Drupal::request()->query;
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
    $mapping = [
      'all' => '',
      'student' => 'student',
      'faculty_staff' => 'faculty/staff',
    ];

    $rows = [];

    $data = \Drupal::database()
      ->select('correspondence_archives', 'c')
      ->fields('c')
      ->condition('tags', '%' . $mapping[$audience] . '%', 'LIKE')
      ->execute();
    foreach ($data as $row) {
      $rows[] = [
        $row->date,
        $row->from_name,
        Link::fromTextAndUrl($row->subject, Url::fromUri($row->url)),
        $row->audience,
      ];
    }

    $headers = [
      'Created',
      'From',
      'Email',
      'Audience',
    ];

    $form['audience'] = [
      '#type' => 'select',
      '#title' => t('Audience'),
      '#description' => t('The intended audience for the communications.'),
      '#default_value' => $audience,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'registrar-core-correspondence-table',
        'effect' => 'fade',
      ],
      '#options' => [
        'all' => 'All',
        'student' => 'Student',
        'faculty_staff' => 'Faculty/Staff',
      ],
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
