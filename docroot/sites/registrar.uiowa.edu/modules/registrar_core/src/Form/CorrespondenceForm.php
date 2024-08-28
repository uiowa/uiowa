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

    $params = \Drupal::request()->query->get('keys');

    if ($form_state->getValue('audience')) {
      $audience = $form_state->getValue('audience');
    }
    elseif (isset($params['audience'])) {
      $audience = $params['audience'];
    }
    else {
      $audience = 'all';
    }

    $rows = [];
    $endpoint = 'https://apps.its.uiowa.edu/dispatch/api/v1/';
    $dispatch_params = [
      'visible' => 'true',
      'tag' => 'registrar',
    ];
    $query = UrlHelper::buildQuery($dispatch_params);
    $archives = $this->dispatchGetData($endpoint . "archives?{$query}");

    foreach ($archives as $archive_url) {
      $archive = $this->dispatchGetData($archive_url);
      $communication = $this->dispatchGetData($archive->communication);

      // Filter out ones that don't match our filter tag,
      // if "all" was not selected.
      if ($audience !== 'all') {
        $matches = [
          'student' => 'student',
          'faculty_staff' => 'faculty/staff',
        ];
        $campaign = $this->dispatchGetData($communication->campaign);
        if (in_array($matches[$audience], $campaign->tags)) {
          continue;
        }
      }

      $date = date('m/d/Y', strtotime($archive->createdOn));
      $population = $this->dispatchGetData($communication->population);
      $key = basename($archive_url);

      $rows[] = [
        $date,
        $communication->email->fromName,
        Link::fromTextAndUrl($communication->email->subject, Url::fromUri("https://apps.its.uiowa.edu/dispatch/archive/{$key}")),
        $population->name,
      ];
    }

    $headers = [
      'Created',
      'From',
      'Email',
      'Intended Population',
    ];

    $form['audience'] = [
      '#type' => 'select',
      '#title' => t('Audience'),
      '#description' => t('audience by audience.'),
      '#default_value' => $audience,
      '#ajax' => [
        'callback' => 'ajaxCallback',
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

  /**
   * Helper function to get dispatch data.
   *
   * @param $endpoint
   *
   * @return object
   */
  function dispatchGetData($endpoint) {
    $data = FALSE;
    $hash = base64_encode($endpoint);
    $cid = "registrar_core_correspondence:request:{$hash}";
    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      $options = [
        'headers' => [
          'x-dispatch-api-key' => $this->dispatch->getKey(),
          'Accept' => 'application/json',
        ],
      ];
      try {
        $data = $this->dispatch->get($endpoint, $options);
      }
      catch (RequestException | GuzzleException $e) {
        // @todo Add logger service for error reporting.
        $this->logger->error('Error encountered getting data from @endpoint: @code @error', [
          '@endpoint' => $endpoint,
          '@code' => $e->getCode(),
          '@error' => $e->getMessage(),
        ]);
      }

      if ($data) {
        // Cache for 12 hours.
        $this->cache->set($cid, $data, time() + 43200);
      }
    }

    return $data;
  }

}
