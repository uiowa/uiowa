<?php

namespace Drupal\uiowa_search\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements an example form.
 */
class SearchForm extends FormBase {

  /**
   * The uiowa_search config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config.factory service.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('uiowa_search.settings');
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['search_terms'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#label_attributes' => [
        'class' => [
          'sr-only',
        ],
      ],
      '#maxlength' => '256',
      '#size' => '15',
    ];

    $placeholder = $this->t('Search');

    if ($this->config->get('uiowa_search.cse_scope') === 1) {
      $placeholder = $this->t('Search this site');
    }
    elseif ($this->config->get('uiowa_search.cse_scope') === 0) {
      $placeholder = $this->t('Search all University of Iowa');
    }

    $form['search_terms']['#attributes'] = [
      'placeholder' => $placeholder,
    ];

    $form['submit_search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#name' => 'btnG',
    ];

    $form['#action'] = Url::fromRoute('uiowa_search.search_results')->toString();

    $form['#attributes']['class'][] = 'uiowa-search--search-form';
    $form['#attributes']['class'][] = 'search-google-appliance-search-form';
    $form['#attributes']['aria-label'] = 'site search';
    $form['#attributes']['role'] = 'search';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::fromRoute('uiowa_search.search_results', [], [
      'query' => [
        'terms' => $form_state->getValue('search_terms'),
      ],
    ]));
  }

}
