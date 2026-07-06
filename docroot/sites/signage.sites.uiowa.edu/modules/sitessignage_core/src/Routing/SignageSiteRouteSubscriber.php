<?php

namespace Drupal\sitessignage_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class SignageSiteRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $restricted_routes = [
      // Core account creation form.
      'user.admin_create',
    ];

    // Restrict access to these routes for non-admins.
    foreach ($restricted_routes as $restricted_route) {
      if ($route = $collection->get($restricted_route)) {
        $route->setRequirement('_uiowa_core_access_check', 'TRUE');
      }
    }
  }

}
