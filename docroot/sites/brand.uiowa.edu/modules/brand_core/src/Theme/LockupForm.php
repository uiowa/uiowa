<?php

namespace Drupal\brand_core\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Use uiowa_bootstrap theme for Lockup form instead of admin theme.
 */
class LockupForm implements ThemeNegotiatorInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($route_match->getRouteName() == 'node.add') {
      $node_type = $route_match->getRawParameter('node_type');
      if ($node_type == 'lockup') {
        return TRUE;
      }
    }
    elseif ($route_match->getRouteName() == 'entity.node.edit_form') {
      $node = $route_match->getParameter('node');
      if ($node->bundle() == 'lockup') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return 'uids_base';
  }

}
