<?php

namespace Drupal\uiowa_hours\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\uiowa_core\HeadlineHelper;
use Drupal\uiowa_hours\HoursApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Hours block.
 *
 * @Block(
 *   id = "uiowa_hours",
 *   admin_label = @Translation("Hours"),
 *   category = @Translation("Site custom")
 * )
 */
class HoursBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Hours API service.
   *
   * @var \Drupal\uiowa_hours\HoursApi
   */
  protected $hours;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Override the construction method.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\uiowa_hours\HoursApi $hours
   *   The Hours API service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form_builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, HoursApi $hours, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->hours = $hours;
    $this->formBuilder = $formBuilder;
  }

  /**
   * Override the create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The application container.
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uiowa_hours.api'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $build['heading'] = [
      '#theme' => 'uiowa_core_headline',
      '#headline' => $config['headline'],
      '#hide_headline' => $config['hide_headline'],
      '#heading_size' => $config['heading_size'],
      '#headline_style' => $config['headline_style'],
    ];

    if ($config['display_datepicker'] == TRUE) {
      $build['form'] = $this->formBuilder->getForm('Drupal\uiowa_hours\Form\HoursFilterForm', $config['resource_name']);
    }
    else {
      $date = 'Today';
      $start = date('m/d/Y', strtotime($date));
      $params = [
        'start' => $start,
      ];
      $result = $this->hours->getHours($config['resource_name'], $params);
      // @todo Make render array better.
      $build['content'] = $result;
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    $form['headline'] = HeadlineHelper::getElement([
      'headline' => $config['headline'] ?? NULL,
      'hide_headline' => $config['hide_headline'] ?? 0,
      'heading_size' => $config['heading_size'] ?? 'h2',
      'headline_style' => $config['headline_style'] ?? 'default',
      'child_heading_size' => $config['child_heading_size'] ?? 'h3',
    ]);

    $form['resource_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resource Name'),
      '#required' => TRUE,
      '#description' => $this->t('Exchange resource name (e.g. RES-REC-CRWC)'),
      '#default_value' => $config['resource_name'] ?? '',
    ];

    $form['display_datepicker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Datepicker'),
      '#description' => $this->t('Allow user to filter by date.'),
      '#default_value' => $this->configuration['display_datepicker'] ?? FALSE,
    ];

    $form['display_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display open/closed status information'),
      '#description' => $this->t('Display a open/closed status indicator.'),
      '#default_value' => $this->configuration['display_status'] ?? FALSE,
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Alter the headline field settings for configuration.
    foreach ($form_state->getValues()['headline']['container'] as $name => $value) {
      $this->configuration[$name] = $value;
    }
    $this->configuration['resource_name'] = $form_state->getValue('resource_name');
    $this->configuration['display_datepicker'] = $form_state->getValue('display_datepicker');
    $this->configuration['display_status'] = $form_state->getValue('display_status');
    parent::blockSubmit($form, $form_state);
  }

}
