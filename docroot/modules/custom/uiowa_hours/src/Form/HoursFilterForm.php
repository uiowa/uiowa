<?php

namespace Drupal\uiowa_hours\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_hours\HoursApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a uiowa_hours filter form.
 */
class HoursFilterForm extends FormBase {
  /**
   * The Hours API service.
   *
   * @var \Drupal\uiowa_hours\HoursApi
   */
  protected $hours;

  /**
   * HoursFilterForm constructor.
   *
   * @param \Drupal\uiowa_hours\HoursApi $hours
   *   The Hours API service.
   */
  public function __construct(HoursApi $hours) {
    $this->hours = $hours;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_hours.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    static $count;
    $count++;
    return 'uiowa_hours_filter_form_' . $count;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $resource_name = NULL) {
    $today = strtotime('Today');
    $form['#attached']['library'][] = 'uiowa_hours/uiowa-hours-finishedinput';
    $form['#attributes']['class'][] = 'form-inline clearfix uiowa-hours-filter-form';

    $form['resource_name'] = [
      '#type' => 'hidden',
      '#value' => $resource_name,
    ];

    // Date field with custom delayed ajax callback.
    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Filter by date'),
      '#default_value' => date('Y-m-d', $today),
      '#ajax' => [
        'callback' => [$this, 'dateFilterCallback'],
        'event' => 'finishedinput',
      ],
    ];

    // Get today for initial result.
    $start = date('m/d/Y', $today);
    $params = [
      'start' => $start,
    ];
    $result = $this->hours->getHours($resource_name, $params);

    $form['result'] = [
      '#type' => 'item',
      '#wrapper_attributes' => [
        'role' => 'region',
        'aria-live' => 'assertive'
      ],
      '#markup' => $result['#markup'],
    ];

    return $form;
  }

  /**
   * Date Filter Callback.
   */
  public function dateFilterCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $date = $form_state->getValue('date');
    $resource_name = $form_state->getValue('resource_name');
    $start = date('m/d/Y', strtotime($date));
    $params = [
      'start' => $start,
    ];
    $result = $this->hours->getHours($resource_name, $params);
    $response->addCommand(new HtmlCommand('#edit-result', $result));
    $message = $this->t('Returning resource hours information for @date.', ['@date' => $start]);
    $response->addCommand(new AnnounceCommand($message, 'polite'));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

}
