<?php

namespace Drupal\emergency_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\uiowa_core\HeadlineHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An Active Alerts block.
 *
 * @Block(
 *   id = "active_alerts_block",
 *   admin_label = @Translation("Active Alerts Block"),
 *   category = @Translation("Site custom"),
 * )
 */
class ActiveAlertsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an ActiveAlertsBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'headline' => NULL,
      'hide_headline' => 0,
      'heading_size' => 'h2',
      'headline_style' => 'default',
      'headline_alignment' => 'default',
      'child_heading_size' => 'h2',
    ];
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
      'headline_alignment' => $config['headline_alignment'] ?? 'default',
      'child_heading_size' => $config['child_heading_size'] ?? 'h2',
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    foreach ($form_state->getValues()['headline']['container'] as $name => $value) {
      $this->configuration[$name] = $value;
    }
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $source = $this->configFactory->get('uiowa_alerts.settings')->get('hawk_alert.source');

    $build['heading'] = [
      '#theme' => 'uiowa_core_headline',
      '#headline' => $config['headline'],
      '#hide_headline' => $config['hide_headline'],
      '#heading_size' => $config['heading_size'],
      '#headline_style' => $config['headline_style'],
      '#headline_alignment' => $config['headline_alignment'] ?? 'default',
    ];

    $build['alerts'] = [
      '#markup' => '<div class="active-alerts-container"><p class="loading">Loading active alerts...</p></div>',
      '#attached' => [
        'library' => [
          'emergency_core/active_alerts',
          'uids_base/card',
        ],
        'drupalSettings' => [
          'uiowaAlerts' => [
            'source' => $source,
          ],
        ],
      ],
    ];

    return $build;
  }

}
