<?php

namespace Drupal\uiowa_alerts\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a block of alerts.
 *
 * @Block(
 *   id = "uiowa_alerts_block",
 *   admin_label = @Translation("Alerts block"),
 * )
 */
class AlertsBlock extends BlockBase {

  /**
   * The config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs an AlertsBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config;
  }

  /**
   * {@inheritDoc}
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
  public function build() {
    $config = $this->config->get('uiowa_alerts.settings');
    $no_alerts_message = trim($config->get('no_alerts_message'));
    $filtered_message = check_markup($no_alerts_message, 'minimal');
    $source = $config->get('source');

    switch ($source) {
      case 'json_production':
        $source_url = 'https://emergency.uiowa.edu/api/active.json';
        break;

      case 'json_test':
        $source_url = 'https://emergency.stage.drupal.uiowa.edu/api/active.json';
        break;
    }
    return [
      '#markup' => '<div class="uiowa-alerts-wrapper"></div>',
      '#attached' => [
        'library' => [
          'uiowa_alerts/uiowa-alerts',
        ],
        'drupalSettings' => [
          'uiowaAlerts' => [
            'alertSource' => $source_url,
            'noAlertsMessage' => $filtered_message,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
