<?php

namespace Drupal\uiowa_core\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
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
  public function buildForm(array $form, FormStateInterface $form_state, array $search_config = []) {
    $form['#attributes']['class'][] = 'form-inline clearfix uiowa-search-form';

    $form['search_config'] = [
      '#type' => 'hidden',
      '#value' => $search_config,
    ];

    $form['search'] = [
      '#type' => 'search',
      '#title' => $search_config['search_label'] ?? $this->t('Search'),
      '#placeholder' => $search_config['search_label'] ?? $this->t('Search'),
      '#size' => 30,
      '#maxlength' => 255,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $search_config['button_text'] ?? $this->t('Search'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $uri = $values['search_config']['endpoint'];
    $query = $values['search'];
    $prepend = $values['search_config']['query_prepend'];

    // Support root-relative URLs.
    if (str_starts_with($uri, '/')) {
      $uri = 'base:' . substr($uri, 1);
    }

    $url = Url::fromUri($uri, ['query' =>
      [$values['search_config']['query_parameter'] => $prepend, $query, [$values['search_config']['additional_query']]]
    ]);

    if (UrlHelper::isExternal($uri)) {
      $response = new TrustedRedirectResponse($url->toString());
      $form_state->setResponse($response);
    }
    else {
      $form_state->setRedirectUrl($url);
    }
  }

}
