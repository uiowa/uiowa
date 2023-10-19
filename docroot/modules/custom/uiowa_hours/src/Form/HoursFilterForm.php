<?php

namespace Drupal\uiowa_hours\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_core\HeadlineHelper;
use Drupal\uiowa_hours\HoursApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an uiowa_hours filter form.
 */
class HoursFilterForm extends FormBase {
  /**
   * The Hours API service.
   */
  protected HoursApi $hours;

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
    $form['#attached']['library'][] = 'uiowa_hours/filter_form';
    $form['#attributes']['class'][] = 'form-inline clearfix uiowa-hours-filter-form';

    if (empty($config['headline'])) {
      $child_heading_size = $config['child_heading_size'];
    }
    else {
      $child_heading_size = HeadlineHelper::getHeadingSizeUp($config['heading_size']);
    }

    $block_config = [
      'resource' => $config['resource'],
      'display_summary' => $config['display_summary'],
      'child_heading_size' => $child_heading_size,
    ];

    $form['block_config'] = [
      '#type' => 'hidden',
      '#value' => $block_config,
    ];

    // Date field with custom delayed ajax callback.
    if ((int) $config['display_datepicker'] === 1) {
      $form['#attached']['library'][] = 'uiowa_hours/finishedinput';

      // The default value will be set via JS.
      $form['date'] = [
        '#type' => 'date',
        '#title' => $this->t('Filter by date'),
        '#default_value' => NULL,
        '#ajax' => [
          'callback' => [$this, 'dateFilterCallback'],
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];
    }

    $form_id = $form_state->getBuildInfo()['form_id'];
    $result_id = $form_id . '_result';

    $form['results'] = [
      '#type' => 'container',
      '#attributes' => [
        'role' => 'region',
        'aria-live' => 'assertive',
        'id' => $result_id,
        'class' => [
          'uiowa-hours-container',
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => '::dateFilterCallback',
        'wrapper' => 'results-container',
        'disable-refocus' => TRUE,
      ],
      '#attributes' => [
        'class' => [
          'element-invisible',
        ],
        'tabindex' => -1,
      ],
    ];

    return $form;
  }

  /**
   * Date Filter Callback.
   */
  public function dateFilterCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $date = $form_state->getValue('date') ?? date('Y-m-d');
    $block_config = $form_state->getValue('block_config');
    $form_id = $form_state->getBuildInfo()['form_id'];
    $result_id = $form_id . '_result';
    $result = $this->hours->getHours($block_config['resource'], $date, $date);
    $formatted_results = $this->hoursRender($result, $result_id, $block_config);

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#' . $result_id, $formatted_results));
    $message = $this->t('Returning resource hours information for @date.', ['@date' => $date]);
    $response->addCommand(new AnnounceCommand($message, 'polite'));

    return $response;
  }

  /**
   * Builds hours output portion of the form.
   *
   * @param array $result
   *   The result from the HoursApi data request.
   * @param string $result_id
   *   Unique identifier for the output.
   * @param array $block_config
   *   Additional configuration needed for render.
   *
   * @return array
   *   The render array output.
   *
   * @see self::buildForm()
   */
  protected function hoursRender(array $result, string $result_id, array $block_config): array {
    $data = $result['data'];
    $start = $result['query']['start'];
    $end = $result['query']['end'];

    $attributes = [];
    $attributes['class'] = [
      'uiowa-hours',
      'headline--serif',
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
        '#type' => 'card',
        '#attributes' => $attributes,
        '#title' => $this->t('@start@end', [
          '@start' => date('F j, Y', $start),
          '@end' => $end === $start ? NULL : ' - ' . date('F j, Y', $end),
        ]),
        '#content' => [
          'times' => [
            '#markup' => $this->t('<span class="badge badge--orange">Closed</span>'),
          ],
        ],
        '#headline_level' => $block_config['child_heading_size'],
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
          '#type' => 'card',
          '#attributes' => $attributes,
          '#title' => date('F j, Y', strtotime($key)),
          '#content' => [
            'times' => [
              '#theme' => 'item_list',
              '#items' => [],
              '#attributes' => [
                'class' => 'element--list-none element--margin-none',
              ],
            ],
          ],
          '#headline_level' => $block_config['child_heading_size'],
        ];

        foreach ($date as $time) {
          // Mark as closed if "Closure" category is present, else mark as open.
          if (in_array('Closure', $time['categories'])) {
            $badge = 'badge--orange';
            $status = 'Closed';
          }
          else {
            $badge = 'badge--green';
            $status = 'Open';
          }
          $markup = $this->t('<span class="badge @badge">@status</span> @start - @end', [
            '@badge' => $badge,
            '@status' => $status,
            '@start' => date('g:ia', strtotime($time['startHour'])),
            '@end' => date('g:ia', '00:00:00' ? strtotime($time['endHour'] . ', +1 day') : strtotime($time['endHour'])),
          ]);

          // Display time summary alongside hours info if block is set to do so.
          if ($block_config['display_summary'] == 1) {
            $markup .= ' - ' . $time['summary'];
          }

          $render['hours'][$key]['#content']['times']['#items'][] = [
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
