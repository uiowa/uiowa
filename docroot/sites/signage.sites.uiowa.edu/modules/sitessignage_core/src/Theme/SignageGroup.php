<?php

namespace Drupal\sitessignage_core\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\node\NodeInterface;

/**
 * Choose the theme for the Signage Group display.
 */
class SignageGroup implements ThemeNegotiatorInterface {

  /**
   * The config factory service.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // Only apply for our signage group content.
    $node = $route_match->getParameter('node');
    return $node instanceof NodeInterface && $node->bundle() === 'signage_group';
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    if ($this->applies($route_match)) {
      return $this->configFactory->get('system.theme')->get('admin');
    }

    return $this->configFactory->get('system.theme')->get('default');
  }

}
