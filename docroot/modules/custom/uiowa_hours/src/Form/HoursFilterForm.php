<?php

namespace Drupal\uiowa_hours\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a basic filtering form.
 */
class HoursFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_hours_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $filter_config = []) {
    $form['#attributes']['class'][] = 'form-inline clearfix uiowa-hours-filter-form';

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Filter by date'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
  }

}
