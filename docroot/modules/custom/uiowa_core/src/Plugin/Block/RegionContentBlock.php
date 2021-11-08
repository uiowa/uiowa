<?php

namespace Drupal\uiowa_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Region content block' Block.
 *
 * @Block(
 *   id = "region_content_block",
 *   admin_label = @Translation("Region content block"),
 *   category = @Translation("Restricted"),
 * )
 */
class RegionContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegionContentBlock {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Inspired by https://drupal.stackexchange.com/a/239317.
    $block_id = $form["id"]["#value"] ?? $form['id']['#default_value'];
    $this->configuration['block_id'] = $block_id;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $block_id = $config['block_id'];
    $uiowa_core_settings = $this->config->get('uiowa_core.settings');
    $fid = $uiowa_core_settings->get('uiowa_core.region_content.' . $block_id);
    $fragment = NULL;
    if ($fid != NULL) {
      $fragment = $this->entityTypeManager->getStorage('fragment')->load($fid);
//      $fragment['#contextual_links'] => [
//        'region_setttings' => [
//          'route_parameters' => ['regions_settings' => 'region_settings'],
//        ],
//      ];
    }
    return $fragment != NULL ? $this->entityTypeManager->getViewBuilder('fragment')->view($fragment, 'default') : [];
  }

}
