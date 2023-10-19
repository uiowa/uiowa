<?php

namespace Drupal\sitenow_articles\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A preserved links message block.
 *
 * @Block(
 *   id = "preservedlinksmessage_block",
 *   admin_label = @Translation("Preserved Links Message Block"),
 *   category = @Translation("Restricted")
 * )
 */
class PreservedLinksMessage extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config service.
   */
  protected ConfigFactoryInterface $config;

  /**
   * Constructs a PreservedLinksMessage object.
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
  public function __construct(array $configuration, string $plugin_id, mixed $plugin_definition, ConfigFactoryInterface $config) {
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
    $config = $this->config->get('sitenow_articles.settings');

    $markup = $config->get('preserved_links_message_display_default');
    if (!empty($config->get('preserved_links_message_display'))) {
      $markup = $config->get('preserved_links_message_display');
    }

    return [
      '#markup' => $markup,
      '#attributes' => [
        'class' => [
          'alert',
          'alert-info',
        ],
      ],
    ];
  }

}
