<?php

namespace Drupal\transportation_calculator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Transportation Cost Calculator form.
 */
class CostCalculatorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    static $count;
    $count++;
    return 'transportation_calculator_cost_calculator_' . $count;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = $this->getFormId() . '-wrapper';

    $form = [
      '#prefix' => '<div id="' . $wrapper_id . '" aria-live="polite">',
      '#suffix' => '</div>',
      '#attached' => [
        'library' => [
          'transportation_calculator/transportation_calculator',
        ],
      ],
      'distance' => [
        '#type' => 'number',
        '#min' => 0,
        '#step' => 0.01,
        '#title' => $this->t('Distance'),
        '#description' => $this->t('What is your daily round trip commute distance?'),
        '#field_suffix' => $this->t('Miles'),
        '#default_value' => $this->config('transportation_calculator.settings')->get('distance') ?? 45,
      ],
      'days_travel' => [
        '#title' => $this->t('Days of travel'),
        '#type' => 'number',
        '#min' => 0,
        '#max' => 31,
        '#step' => 0.5,
        '#description' => $this->t('How many days a month do you normally travel to work?'),
        '#field_suffix' => $this->t('Days'),
        '#default_value' => $this->config('transportation_calculator.settings')->get('days-of-travel') ?? 21,
      ],
      'aaa_cost_per_mile' => [
        '#title' => $this->t('AAA cost per mile'),
        '#type' => 'number',
        '#min' => 0,
        '#description' => $this->t('Based on <a href="@aaa">AAA’s average cost per mile</a> for operating a vehicle 15,000 miles per year.', ['@aaa' => 'https://www.aaa.com/autorepair/articles/what-does-it-cost-to-own-and-operate-a-car']),
        '#field_prefix' => $this->t('$'),
        '#default_value' => $this->config('transportation_calculator.settings')->get('aaa-cost') ?? 0.57,
        '#disabled' => TRUE,
        '#step' => 0.0001,
      ],
      'cost_to_park' => [
        '#title' => $this->t('Parking Cost'),
        '#type' => 'number',
        '#min' => 0,
        '#description' => $this->t('How much do you currently pay for monthly parking?'),
        '#field_prefix' => $this->t('$'),
        '#default_value' => $this->config('transportation_calculator.settings')->get('parking-cost') ?? 62,
        '#step' => 0.1,
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => 'Submit',
        '#ajax' => [
          'callback' => [$this, 'calculateCost'],
          'wrapper' => $wrapper_id,
          'method' => 'html',
          'disable-refocus' => TRUE,
          'effect' => 'fade',
        ],
        '#attributes' => [
          'class' => [
            'bttn--primary',
          ],
        ],
      ],
      'results' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'results-wrapper',
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * AJAX form callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form results.
   */
  public function calculateCost(array &$form, FormStateInterface $form_state): array {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }
    $monthly = $form_state->getValue('distance') * $form_state->getValue('days_travel') * $form_state->getValue('aaa_cost_per_mile') + $form_state->getValue('cost_to_park');
    $yearly = $monthly * 12;

    $van_base_rate = $this->config('transportation_calculator.settings')->get('van-base-rate') ?? 10.44;
    $van_mileage_rate = $this->config('transportation_calculator.settings')->get('van-mileage-rate') ?? 0.2252;
    $van_average_working_days = $this->config('transportation_calculator.settings')->get('average-working-days') ?? 21;
    $van_maximum_riders = $this->config('transportation_calculator.settings')->get('maximum-van-riders') ?? 6;
    $vanpool = ($van_base_rate + $van_mileage_rate * $form_state->getValue('distance') * $van_average_working_days) * 12 / $van_maximum_riders;

    $upass_cost = $this->config('transportation_calculator.settings')->get('upass-cost') ?? 15;
    $upass_yearly = $upass_cost * 12;

    $express_380_cost = $this->config('transportation_calculator.settings')->get('380-express') ?? 690;

    $form['results']['costs'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'costs',
        ],
      ],
      'monthly' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'costs-monthly',
          ],
        ],
        'item' => [
          '#type' => 'item',
          '#title' => $this->t('Monthly commute costs'),
          '#markup' => $this->t('<span>@monthly</span>', [
            '@monthly' => number_format($monthly, 2),
          ]),
          '#field_prefix' => '$',
        ],
      ],
      'yearly' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'costs-yearly',
          ],
        ],
        'item' => [
          '#type' => 'item',
          '#title' => $this->t('Yearly commute costs'),
          '#markup' => $this->t('<span>@yearly</span>', [
            '@yearly' => number_format($yearly, 2),
          ]),
          '#field_prefix' => '$',
        ],
      ],
    ];

    $form['results']['savings'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'savings',
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#caption' => $this->t('Depending on your distance from campus, some modes may not apply.'),
        '#header' => [
          $this->t('Mode of Transportation'), $this->t('Cost Per Year'), $this->t('Yearly Savings'),
        ],
        '#rows' => [
          [
            'mode' => $this->t('CAMBUS'),
            'cost' => '$' . number_format(0, 2),
            'savings' => '$' . number_format($yearly, 2),
          ],
          [
            'mode' => $this->t('Parking'),
            'cost' => '$' . number_format($yearly, 2),
            'savings' => '$' . number_format(0, 2),
          ],
          [
            'mode' => $this->t('Vanpool'),
            'cost' => '$' . number_format($vanpool, 2),
            'savings' => '$' . number_format($yearly - $vanpool, 2),
          ],
          [
            'mode' => $this->t('Carpool'),
            'cost' => '$' . number_format($yearly / 2, 2),
            'savings' => '$' . number_format($yearly / 2, 2),
          ],
          [
            'mode' => $this->t('380 Express'),
            'cost' => '$' . number_format($express_380_cost, 2),
            'savings' => '$' . number_format($yearly - $express_380_cost, 2),
          ],
          [
            'mode' => $this->t('Bus Pass (U-PASS)'),
            'cost' => '$' . number_format($upass_yearly, 2),
            'savings' => '$' . number_format($yearly - $upass_yearly, 2),
          ],
          [
            'mode' => $this->t('Bike/Walk'),
            'cost' => '$0.00',
            'savings' => '$' . number_format($yearly, 2),
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // no-op.
  }

}
