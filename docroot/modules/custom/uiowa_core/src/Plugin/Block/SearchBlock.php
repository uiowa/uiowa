<?php

namespace Drupal\uiowa_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a basic search block.
 *
 * @Block(
 *   id = "uiowa_core_search_block",
 *   admin_label = @Translation("Search Block"),
 *   category = @Translation("Site custom")
 * )
 */
class SearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form_builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $search_config = [
      'endpoint' => $config['endpoint'],
      'query_parameter' => $config['query_parameter'],
      'button_text' => $config['button_text'],
      'search_label' => $config['search_label'],
    ];
    $form_state = new FormState();
    $form_state->addBuildInfo('search_config', $search_config);
    return $this->formBuilder->buildForm('Drupal\uiowa_core\Form\SearchBlock', $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    $form['endpoint'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Endpoint Path'),
      '#description' => $this->t('Start typing to see a list of results. Click to select.'),
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => 'default',
      ],
      '#default_value' => isset($config['endpoint']) ? $config['endpoint'] : '/search',
      '#required' => TRUE,
    ];
    $form['query_parameter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query Parameter'),
      '#description' => $this->t('<em>title</em> is common for content filtering, <em>terms</em> is used for search on this site'),
      '#default_value' => isset($config['query_parameter']) ? $config['query_parameter'] : 'terms',
    ];
    $form['search_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Label'),
      '#default_value' => isset($config['search_label']) ? $config['search_label'] : 'Search',
      '#required' => TRUE,
    ];
    $form['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button Text'),
      '#default_value' => isset($config['button_text']) ? $config['button_text'] : 'Search',
      '#required' => TRUE,
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['endpoint'] = $values['endpoint'];
    $this->configuration['query_parameter'] = $values['query_parameter'];
    $this->configuration['button_text'] = $values['button_text'];
    $this->configuration['search_label'] = $values['search_label'];
  }

}
