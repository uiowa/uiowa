<?php

namespace Drupal\safety_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\safety_core\Controller\CleryController;

/**
 * Provides a Fire Log block.
 *
 * @Block(
 *   id = "fire_log_block",
 *   admin_label = @Translation("Fire log"),
 *   category = @Translation("Site custom")
 * )
 */
class FireLogBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Clery controller service.
   *
   * @var \Drupal\safety_core\Controller\CleryController
   */
  protected $cleryController;

  /**
   * Constructs a new FireLogBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CleryController $clery_controller,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cleryController = $clery_controller;
  }

  /**
   * Creates an instance of the plugin.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('safety_core.clery_controller'),
    );
  }

  /**
   * Builds the render array for this block.
   */
  public function build() {
    $build = [];
    return $build;
  }

  /**
   * Gets cache contexts for this block.
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.query_args']);
  }

  /**
   * Gets cache tags for this block.
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['fire_log_data']);
  }

  /**
   * Gets the cache max age for this block.
   */
  public function getCacheMaxAge() {
    return 1800;
  }

}
