<?php

namespace Drupal\sitenow_people\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Make this view use the admin theme by mocking an admin route.
    if ($route = $collection->get('view.people_sort.page_sort_people_tag')) {
      $route->setOption('_admin_route', TRUE);
    }
  }

}
