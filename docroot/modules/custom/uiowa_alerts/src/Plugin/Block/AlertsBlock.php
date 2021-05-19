<?php

namespace Drupal\uiowa_alerts\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block of alerts.
 *
 * @Block(
 *   id = "uiowa_alerts_block",
 *   admin_label = @Translation("Alerts block"),
 * )
 */
class AlertsBlock extends BlockBase implements ContainerFactoryPluginInterface {

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

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'uiowa-alerts-wrapper',
        ],
      ],
    ];

    if ($config->get('hawk_alert.display')) {
      $source = $config->get('hawk_alert.source');

      $build['hawk_alerts'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => 'hawk-alerts-wrapper',
        ],
        '#attached' => [
          'library' => [
            'uiowa_alerts/uiowa-alerts',
          ],
          'drupalSettings' => [
            'uiowaAlerts' => [
              'source' => $source,
            ],
          ],
        ],
      ];
    }

    if ($config->get('custom_alert.display')) {
      $message = trim($config->get('custom_alert.message'));
      $filtered_message = check_markup($message, 'minimal');
      $level = $config->get('custom_alert.level');

      $build['custom_alert'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'custom-alert-wrapper',
          ],
        ],
        'alert_wrapper' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'alert',
              "alert-{$level}",
            ],
            'role' => 'region',
            'aria-label' => ($level == 'danger') ? 'alert message' : "{$level} message",
          ],
          'message_wrapper' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => 'hawk-alert-message',
            ],
            'message' => [
              '#markup' => $filtered_message,
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      $this->config->get('uiowa_alerts.settings')->getCacheTags()
    );
  }

}
