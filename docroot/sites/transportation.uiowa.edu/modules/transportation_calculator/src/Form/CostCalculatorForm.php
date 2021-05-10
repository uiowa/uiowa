<?php

namespace Drupal\transportation_calculator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;

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
    $wrapper = Html::getUniqueId('calculator-results');

    return [
      '#attached' => [
        'library' => [
          'transportation_calculator/transportation_calculator',
        ],
      ],
      'distance' => [
        '#type' => 'number',
        '#title' => $this->t('Distance'),
        '#description' => $this->t('What is your daily round trip commute distance?'),
        '#field_suffix' => $this->t('Miles'),
        '#default_value' => 45,
      ],
      'days_travel' => [
        '#title' => $this->t('Days of travel'),
        '#type' => 'number',
        '#description' => $this->t('How many days a month do you normally travel to work?'),
        '#field_suffix' => $this->t('Days'),
        '#default_value' => 21,
      ],
      'aaa_cost_per_mile' => [
        '#title' => $this->t('AAA cost per mile'),
        '#type' => 'number',
        '#description' => $this->t('Based on <a href="@aaa">AAA’s average cost per mile</a> for operating a vehicle 15,000 miles per year.', ['@aaa' => 'http://exchange.aaa.com/automobiles-travel/automobiles/driving-costs/#.WH6WfLYrJsZ']),
        '#field_prefix' => $this->t('$'),
        '#default_value' => 0.5899,
        '#disabled' => TRUE,
        '#step' => 0.0001,
      ],
      'cost_to_park' => [
        '#title' => $this->t('Parking Cost'),
        '#type' => 'number',
        '#description' => $this->t('How much do you currently pay for monthly parking?'),
        '#field_prefix' => $this->t('$'),
        '#default_value' => 62,
        '#step' => 0.1,
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => 'Submit',
        '#ajax' => [
          'callback' => [$this, 'calculateCost'],
          'wrapper' => $wrapper,
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
          'id' => $wrapper,
          'aria-live' => 'polite',
        ],
      ],
    ];
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
    $monthly = $form_state->getValue('distance') * $form_state->getValue('days_travel') * $form_state->getValue('aaa_cost_per_mile') + $form_state->getValue('cost_to_park');
    $yearly = $monthly * 12;
    $vanpool = (10.44 + .2252 * $form_state->getValue('distance') * 21) * 12 / 6;
    $upass = 15 * 12;

    $form['results'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'results-wrapper',
        ],
      ],
      'costs' => [
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
      ],
      'savings' => [
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
              'cost' => '$690.00',
              'savings' => '$' . number_format($yearly - 690, 2),
            ],
            [
              'mode' => $this->t('Bus Pass (U-PASS)'),
              'cost' => '$' . number_format($upass, 2),
              'savings' => '$' . number_format($yearly - $upass, 2),
            ],
            [
              'mode' => $this->t('Bike/Walk'),
              'cost' => '$0.00',
              'savings' => '$' . number_format($yearly, 2),
            ],
          ],
        ],
      ],
    ];

    return $form['results'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // no-op.
  }

}
