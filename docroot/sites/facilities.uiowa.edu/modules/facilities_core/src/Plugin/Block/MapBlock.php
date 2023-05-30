<?php

namespace Drupal\facilities_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Map block.
 *
 * @Block(
 *   id = "map_block",
 *   admin_label = @Translation("Map Block"),
 *   category = @Translation("Site custom"),
 * )
 */
class MapBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new map instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('current_route_match'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');

    $longitude = $node->get('field_building_longitude')->getString();
    $latitude = $node->get('field_building_latitude')->getString();

    return [
      '#type' => 'inline_template',
      '#template' => '<iframe width="100%" height="400" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" title="UI Buildings New" src="//uiadmin.maps.arcgis.com/apps/Embed/index.html?webmap=b8916ce59fb74e17893822f62b0db58c&center= ' . $longitude . ',' . $latitude . '&level=9&previewImage=false&scale=true&searchextent=false&disable_scroll=true&theme=light"></iframe>',
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Rebuild block on node save.
    if ($node = \Drupal::routeMatch()->getParameter('node')) {
      // If there is node add its cachetag.
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    else {
      // Return default tags instead.
      return parent::getCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Rebuild block for every new route.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
