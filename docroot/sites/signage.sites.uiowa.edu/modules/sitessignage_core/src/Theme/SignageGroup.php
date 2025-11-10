<?php

namespace Drupal\sitessignage_core\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
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

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $currentUser) {
    $this->configFactory = $config_factory;
    $this->currentUser = $currentUser;
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
    if ($this->applies($route_match) && $this->currentUser->isAuthenticated()) {
      return $this->configFactory->get('system.theme')->get('admin');
    }

    return $this->configFactory->get('system.theme')->get('default');
  }

}
