<?php

namespace Drupal\studentlife_topics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a Topics page search form.
 */
class TopicsSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'topics_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'form-inline clearfix';
    $form['search'] = [
      '#type' => 'search',
      '#title' => $this->t('Search this site'),
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
    $form_state->setRedirectUrl(Url::fromRoute('uiowa_search.search_results', ['title' => $title]));
  }

}
