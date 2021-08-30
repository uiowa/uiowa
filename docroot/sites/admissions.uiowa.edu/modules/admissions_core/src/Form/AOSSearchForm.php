<?php

namespace Drupal\admissions_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a Areas of Study search form.
 */
class AOSSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'area_of_study_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'form-inline clearfix';
    $form['search'] = [
      '#type' => 'search',
      '#title' => $this->t('Search for an Area of Study'),
      '#size' => 30,
      '#maxlength' => 255,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Search'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $title = $values['search'];
    $form_state->setRedirectUrl(Url::fromRoute('view.areas_of_study.areas_of_study', ['title' => $title]));
  }

}
