<?php

namespace Drupal\uiowa_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a basic search form.
 */
class SearchBlock extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_core_search_block';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    $form['#attributes']['class'][] = 'form-inline clearfix uiowa-search-form';
    $form['search'] = [
      '#type' => 'search',
      '#title' => $build_info['search_config']['search_label'],
      '#size' => 30,
      '#maxlength' => 255,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $build_info['search_config']['button_text'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    $values = $form_state->getValues();
    $uri = $build_info['search_config']['endpoint'];
    $query = $values['search'];
    // Support root-relative URLs.
    if (str_starts_with($uri, '/')) {
      $uri = 'base:' . substr($uri, 1);
    }
    $url = Url::fromUri($uri, ['query' => [$build_info['search_config']['query_parameter'] => $query]]);
    $form_state->setRedirectUrl($url);
  }

}
