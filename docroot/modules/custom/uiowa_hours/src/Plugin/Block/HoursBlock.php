<?php

namespace Drupal\uiowa_hours\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
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
   * The config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, HoursApi $hours, FormBuilderInterface $formBuilder, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->hours = $hours;
    $this->formBuilder = $formBuilder;
    $this->configFactory = $configFactory;
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
      $container->get('form_builder'),
      $container->get('config.factory')
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
      $build['form'] = $this->formBuilder->getForm('Drupal\uiowa_hours\Form\HoursFilterForm', $config['resource']);
    }
    else {
      $result = $this->hours->getHours($config['resource']);

      // @todo Figure out how to use cards here.
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
    $group = $this->configFactory->get('uiowa_hours.settings')->get('group');

    // If no group is selected, return form with message.
    if (empty($group)) {
      $form['no-group'] = [
        '#prefix' => '<div>',
        '#suffix' => '</div>',
        '#markup' => $this->t('A resource group must be selected. <a href=":url">Hours configuration</a>.', [
          ':url' => Url::fromRoute('uiowa_hours.settings')->toString(),
        ]),
      ];

      return $form;
    }

    $resources = $this->hours->getResources($group);

    $form['headline'] = HeadlineHelper::getElement([
      'headline' => $config['headline'] ?? NULL,
      'hide_headline' => $config['hide_headline'] ?? 0,
      'heading_size' => $config['heading_size'] ?? 'h2',
      'headline_style' => $config['headline_style'] ?? 'default',
      'child_heading_size' => $config['child_heading_size'] ?? 'h3',
    ]);

    $form['resource'] = [
      '#type' => 'select',
      '#title' => $this->t('Resource'),
      '#description' => $this->t('The resource to display hours for.'),
      '#required' => TRUE,
      '#default_value' => $config['resource'] ?? NULL,
      '#options' => array_combine($resources, $resources),
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
    $this->configuration['resource'] = $form_state->getValue('resource');
    $this->configuration['display_datepicker'] = $form_state->getValue('display_datepicker');
    $this->configuration['display_status'] = $form_state->getValue('display_status');
    parent::blockSubmit($form, $form_state);
  }

}
