<?php

namespace Drupal\emergency_core\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class CacheControlRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Target the specific JSON:API endpoint.
    if ($route = $collection->get('jsonapi.node--hawk_alert.collection')) {
      // Set no_cache to TRUE to prevent caching.
      $route->setOption('no_cache', TRUE);
    }
  }

}
