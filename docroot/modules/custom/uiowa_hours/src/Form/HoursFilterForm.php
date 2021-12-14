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
  public function buildForm(array $form, FormStateInterface $form_state, $config = NULL) {
    $form['#attached']['library'][] = 'uiowa_hours/uiowa-hours-finishedinput';
    $form['#attributes']['class'][] = 'form-inline clearfix uiowa-hours-filter-form';

    $form['resource'] = [
      '#type' => 'hidden',
      '#value' => $config['resource'],
    ];

    // Date field with custom delayed ajax callback.
    if ($config['display_datepicker'] == 1) {
      $form['date'] = [
        '#type' => 'date',
        '#title' => $this->t('Filter by date'),
        '#default_value' => date('Y-m-d'),
        '#ajax' => [
          'callback' => [$this, 'dateFilterCallback'],
          'event' => 'finishedinput',
        ],
      ];
    }

    $result = $this->hours->getHours($config['resource']);
    $form_id = $form_state->getBuildInfo()['form_id'];
    $result_id = $form_id . '_result';
    $form['results'] = [
      '#type' => 'container',
      '#attributes' => [
        'role' => 'region',
        'aria-live' => 'assertive',
      ],
    ];
    $formatted_results = $this->renderResults($result, $result_id, $config['display_summary']);
    $form['results']['result'] = $formatted_results;

    return $form;
  }

  /**
   * Date Filter Callback.
   */
  public function dateFilterCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $date = $form_state->getValue('date');
    $resource = $form_state->getValue('resource');
    $display_summary = $form_state->getValue('display_summary');
    $result = $this->hours->getHours($resource, $date, $date);
    $result_id = $form['results']['result']['#attributes']['id'];
    $formatted_results = $this->renderResults($result, $result_id, $display_summary);
    $response->addCommand(new HtmlCommand('#' . $result_id, $formatted_results));
    $message = $this->t('Returning resource hours information for @date.', ['@date' => $date]);
    $response->addCommand(new AnnounceCommand($message, 'polite'));

    return $response;
  }

  /**
   * Custom render of data.
   */
  public function renderResults($result, $result_id, $display_summary) {
    $data = $result['data'];
    $start = $result['query']['start'];
    $end = $result['query']['end'];
    $card_classes = [
      'uiowa-hours',
      'card--enclosed',
      'card--media-left',
    ];

    $render = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $result_id,
        'class' => [
          'uiowa-hours-container',
        ],
      ],
    ];

    if ($data === FALSE) {
      $data['closed'] = [
        '#markup' => $this->t('<p><i class="fas fa-exclamation-circle"></i> There was an error retrieving hours information. Please try again later or contact the <a href=":link">ITS Help Desk</a> if the problem persists.</p>', [
          ':link' => 'https://its.uiowa.edu/contact',
        ]),
      ];
    }
    elseif (empty($data)) {
      $render['closed'] = [
        '#theme' => 'hours_card',
        '#attributes' => [
          'class' => $card_classes,
        ],
        '#data' => [
          'date' => $this->t('@start@end', [
            '@start' => date('F d, Y', $start),
            '@end' => $end == $start ? NULL : ' - ' . date('F d, Y', $end),
          ]),
          'times' => [
            '#markup' => $this->t('<span class="badge badge--orange">Closed</span>'),
          ],
        ],
      ];
    }
    else {
      // The v2 API indexes events by a string in Ymd format, e.g. 20211209.
      foreach ($data as $key => $date) {
        // Skip dates that start before $start but end on or after.
        if ($key < date('Ymd', $start)) {
          continue;
        }

        // Times within dates are unsorted for some reason.
        uasort($date, function ($a, $b) {
          return strtotime($a['start']) <=> strtotime($b['start']);
        });

        $render['hours'][$key] = [
          '#theme' => 'hours_card',
          '#attributes' => [
            'class' => $card_classes,
          ],
          '#data' => [
            'date' => date('F d, Y', strtotime($key)),
            'times' => [
              '#theme' => 'item_list',
              '#items' => [],
              '#attributes' => [
                'class' => 'element--list-none',
              ],
            ],
          ],
        ];

        // @todo Add block config to get categories and render them here.
        foreach ($date as $time) {
          $markup = $this->t('<span class="badge badge--green">Open</span> @start - @end', [
            '@start' => date('g:ia', strtotime($time['startHour'])),
            '@end' => date('g:ia', '00:00:00' ? strtotime($time['endHour'] . ', +1 day') : strtotime($time['endHour'])),
          ]);
          if ($display_summary == TRUE) {
            $markup .= ' - ' . $time['summary'];
          }
          $render['hours'][$key]['#data']['times']['#items'][] = [
            '#markup' => $markup,
          ];
        }
      }
    }
    return $render;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

}
