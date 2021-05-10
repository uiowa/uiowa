<?php

namespace Drupal\transportation_calculator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Transportation Cost Calculator settings for this site.
 */
class CostCalculatorSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'transportation_calculator_cost_calculator_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['transportation_calculator.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['default'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default form values'),
      '#description' => $this->t('Site visitors may customize these values while using the calculator.'),
      'distance' => [
        '#type' => 'number',
        '#title' => 'Distance',
        '#min' => 0,
        '#description' => $this->t('What is your daily round trip commute distance?'),
        '#field_suffix' => $this->t('Miles'),
        '#default_value' => $this->config('transportation_calculator.settings')->get('distance') ?? 45,
      ],
      'days-of-travel' => [
        '#type' => 'number',
        '#title' => 'Days of travel',
        '#min' => 0,
        '#description' => $this->t('How many days a month do you normally travel to work?'),
        '#field_suffix' => $this->t('Days'),
        '#default_value' => $this->config('transportation_calculator.settings')->get('days-of-travel') ?? 21,
      ],
      'parking-cost' => [
        '#type' => 'number',
        '#title' => 'Parking cost',
        '#min' => 0,
        '#description' => $this->t('How much do you currently pay for monthly parking?'),
        '#field_prefix' => $this->t('$'),
        '#default_value' => $this->config('transportation_calculator.settings')->get('days-of-travel') ?? 21,
      ],
    ];

    $form['fixed'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Calculation values'),
      '#description' => $this->t('Site visitors may not customize these values while using the calculator.'),
      'vanpool' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Van pool values'),
        '#description' => $this->t('Yearly cost of Vanpool = ([van base rate] + [van mileage rate]*[commute distance from form]*[average working days per month]*12)/[maximum van riders]'),
        'van-base-rate' => [
          '#type' => 'number',
          '#title' => 'Van base rate',
          '#min' => 0,
          '#step' => .01,
          '#field_prefix' => $this->t('$'),
          '#default_value' => $this->config('transportation_calculator.settings')->get('van-base-rate') ?? 10.44,
        ],
        'van-mileage-rate' => [
          '#type' => 'number',
          '#title' => 'Van mileage rate',
          '#min' => 0,
          '#step' => .0001,
          '#field_prefix' => $this->t('$'),
          '#default_value' => $this->config('transportation_calculator.settings')->get('days-of-travel') ?? 0.2252,
        ],

        'van-mileage-rate' => [
          '#type' => 'number',
          '#title' => 'Van mileage rate',
          '#min' => 0,
          '#step' => .0001,
          '#field_prefix' => $this->t('$'),
          '#default_value' => $this->config('transportation_calculator.settings')->get('days-of-travel') ?? 0.2252,
        ],
      ],
      'UPASS' => [
        '#type' => 'fieldset',
        '#title' => $this->t('UPASS values'),
        '#description' => $this->t('Yearly cost of UPASS = [monthly UPASS cost]*12'),
        'cost' => [
          '#type' => 'number',
          '#title' => 'Monthly UPASS cost',
          '#min' => 0,
          '#step' => .01,
          '#description' => $this->t('What is your daily round trip commute distance?'),
          '#field_suffix' => $this->t('Miles'),
          '#default_value' => $this->config('transportation_calculator.settings')->get('distance') ?? 45,
        ],
      ],
      'AAA' => [
        '#type' => 'fieldset',
        '#title' => $this->t('AAA values'),
        '#description' => $this->t('Site visitors may not customize these values while using the calculator.'),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('transportation_calculator.settings')
      ->set('distance', $form_state->getValue('distance'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
