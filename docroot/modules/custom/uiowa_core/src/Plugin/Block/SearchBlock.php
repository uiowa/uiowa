<?php

namespace Drupal\uiowa_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
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
    $block = [];
    $config = $this->getConfiguration();
    $params = [];

    // Build a list of parameters, if they are set.
    foreach ([
      'endpoint',
      'query_parameter',
      'query_prepend',
      'additional_parameters',
      'button_text',
      'search_label',
    ] as $param) {
      if (isset($config[$param])) {
        $params[$param] = $config[$param];
      }
    }

    $block['form'] = $this->formBuilder->getForm(
      'Drupal\uiowa_core\Form\SearchBlock',
      $params,
    );

    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>This is a generic search block you can use to send queries to site search or a view filter.</p>'),
    ];
    $form['#attached']['library'][] = 'linkit/linkit.autocomplete';
    $form['endpoint'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Endpoint Path'),
      '#description' => $this->t('Start typing to see a list of results. Click to select. Relative paths are allowed. External links are allowed.'),
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => 'default',
      ],
      '#default_value' => $config['endpoint'] ?? '/search',
      '#required' => TRUE,
    ];
    $form['query_parameter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query Parameter'),
      '#description' => $this->t('<em>title</em> is common for content filtering, <em>terms</em> is used for search on this site'),
      '#default_value' => $config['query_parameter'] ?? 'terms',
    ];
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
      '#description' => $this->t('Additional query parameters for advanced users.'),
    ];
    $form['advanced']['query_prepend'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query Prepend'),
      '#description' => $this->t('A string to prepend to all search queries.'),
      '#default_value' => $config['query_prepend'] ?? '',
    ];
    $form['advanced']['additional_parameters'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional Query Parameters'),
      '#description' => $this->t('Append additional URL parameters to the search. <em>(UTM tracking codes, search filtering, etc.)</em> Do <strong>not</strong> include sensitive information such as API keys, usernames, or passwords.'),
      '#default_value' => $config['additional_parameters'] ?? '',
    ];
    $form['search_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Label'),
      '#default_value' => $config['search_label'] ?? 'Search',
      '#required' => TRUE,
    ];
    $form['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button Text'),
      '#default_value' => $config['button_text'] ?? 'Search',
      '#required' => TRUE,
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    // Check for duplicates because it causes an error on render if not caught.
    $additional_parameters = $form_state->getValue('advanced')['additional_parameters'];
    preg_match_all('@&([^&]+)=[^&]+@is', $additional_parameters, $matches);
    if (count($matches[0]) !== count(array_unique($matches[1]))) {
      $form_state->setError($form['advanced']['additional_parameters'], $this->t('Duplicate parameters found'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['endpoint'] = $values['endpoint'];
    $this->configuration['query_parameter'] = $values['query_parameter'];
    $this->configuration['query_prepend'] = $values['advanced']['query_prepend'];
    $this->configuration['additional_parameters'] = $values['advanced']['additional_parameters'];
    $this->configuration['button_text'] = $values['button_text'];
    $this->configuration['search_label'] = $values['search_label'];
  }

}
