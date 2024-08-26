<?php

namespace Drupal\registrar_core\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
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
   */
  public function __construct(ConfigFactoryInterface $config_factory, protected DispatchApiClientInterface $dispatch) {
    $this->configFactory = $config_factory;
    $this->dispatch = $dispatch;
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
  public function getFormId() {
    return 'correspondence_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $wrapper_id = $this->getFormId() . '-wrapper';
    $form['#prefix'] = '<div id="' . $wrapper_id . '" aria-live="polite">';
    $form['#suffix'] = '</div>';

    $form = [];

    $form['#id'] = 'correspondence-form';

//    $params = drupal_get_query_parameters();

    if ($form_state->getValue('audience')) {
      $audience = $form_state->getValue('audience');
    }
//    elseif (isset($params['audience'])) {
//      $audience = $params['audience'];
//    }
    else {
      $audience = 'all';
    }

    $rows = [];
    $endpoint = 'https://apps.its.uiowa.edu/dispatch/api/v1/';
    $archives = $this->dispatch->get($endpoint . 'archives', []);

    foreach ($archives as $archive_url) {
      $archive = $this->dispatch->get($archive_url);

      if ($archive->hidden === FALSE) {
        $date = date('m/d/Y', strtotime($archive->createdOn));
        $communication = $this->dispatch->get($archive->communication);
        $campaign = $this->dispatch->get($communication->campaign);

        $matches = [
          'all' => 'registrar',
          'student' => 'student',
          'faculty_staff' => 'faculty/staff',
        ];

        if (in_array($matches[$audience], $campaign->tags)) {
          $population = $this->dispatch->get($communication->population);

          $key = basename($archive_url);

          $rows[] = [
            $date,
            $communication->email->fromName,
            Link::fromTextAndUrl($communication->email->subject, Url::fromUri("https://apps.its.uiowa.edu/dispatch/archive/{$key}")),
            $population->name,
          ];
        }
      }
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
  public function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $triggering_element = $form_state->getTriggeringElement();
    $message = 'Form updated';

    switch ($triggering_element['#name']) {
      case 'op':
        $message = $this->t('Returning correspondences.');
        break;

    }

    $response->addCommand(new AnnounceCommand($message, 'polite'));
    $wrapper_id = '#' . $this->getFormId() . '-wrapper';
    $response->addCommand(new ReplaceCommand($wrapper_id, $form));

    return $response;
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
//    $cache_key = 'registrar_core_correspondence' . base64_encode($endpoint);
//    $data = &drupal_static(__FUNCTION__ . ':' . $cache_key);

    if (!isset($data)) {
//      $cache = cache_get($cache_key);
//      if (isset($cache, $cache->data, $cache->expire) && time() < $cache->expire) {
//        $data = $cache->data;
//      } else {
      $options = [
        'headers' => [
          'x-dispatch-api-key' => $this->dispatch->getKey(),
          'accept' => 'application/json',
        ],
      ];
//      $this->dispatch->addAuthToOptions($options);
      $request = $this->dispatch->get($endpoint, $options);
//
      $data = json_decode($request->data);
//        cache_set($cache_key, $data, 'cache', time() + 3600);
      }
//    }

    return $request;
  }

}
