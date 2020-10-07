<?php

namespace Drupal\uiowa_aggregator\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Disable access to OPML.
 */
class RouteSubscriber extends RouteSubscriberBase {
  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('aggregator.opml_add')) {
      $route->setRequirement('_uiowa_core_access_check', 'TRUE');
    }
  }

}
