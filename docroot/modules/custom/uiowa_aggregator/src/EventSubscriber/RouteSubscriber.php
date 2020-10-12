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
    // Remove OPML access until we know what this is.
    if ($route = $collection->get('aggregator.opml_add')) {
      $route->setRequirement('_uiowa_core_access_check', 'TRUE');
    }

    // Set feed add path to match admin breadcrumbs so its easier to navigate.
    if ($route = $collection->get('aggregator.feed_add')) {
      $route->setPath('/admin/config/services/aggregator/add/feed');
    }
  }

}
