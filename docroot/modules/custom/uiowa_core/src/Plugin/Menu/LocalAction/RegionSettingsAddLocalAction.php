<?php

namespace Drupal\uiowa_core\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Modifies the 'Add region item' local action.
 */
class RegionSettingsAddLocalAction extends LocalActionDefault {

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match): array {
    $options = parent::getOptions($route_match);
    // Adds a destination.
    if ($route_match->getRouteName() == 'uiowa_core.region_settings') {
      $options['query']['destination'] = Url::fromRoute('<current>')->toString();
    }
    return $options;
  }

}
