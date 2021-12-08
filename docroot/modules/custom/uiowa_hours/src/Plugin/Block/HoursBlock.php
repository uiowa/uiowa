<?php

namespace Drupal\uiowa_hours\Plugin\Block;

use Drupal\Core\Block\BlockBase;
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
  protected HoursApi $hours;

  /**
   * Override the construction method.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, HoursApi $hours) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->hours = $hours;
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
      $container->get('uiowa_hours.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $build['heading'] = [
      '#theme' => 'uiowa_core_headline',
      '#headline' => $this->configuration['headline'],
      '#hide_headline' => $this->configuration['hide_headline'],
      '#heading_size' => $this->configuration['heading_size'],
      '#headline_style' => $this->configuration['headline_style'],
    ];
    $data = $this->hours->getToday($config['resource_name']);
    $date = 'Today';
    $key = date('Ymd', strtotime($date));
    $markup = t('No hours information available.');
    if ($data->$key) {
      $markup = '';
      $resource_hours = $data->$key;
      foreach ($resource_hours as $time) {
        $start = date('g:i a', strtotime($time->startHour));
        $end = '00:00:00' ? strtotime($time->endHour . ', +1 day') : strtotime($time->endHour);
        $end = date('g:i a', $end);
        $markup .= t($time->summary . ' ' . $start . ' - ' . $end);
      }
    }
    $build['content'] = [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
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
    parent::blockSubmit($form, $form_state);
  }
}
