<?php

namespace Drupal\sitenow\EventSubscriber;

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
    if ($route = $collection->get('system.403')) {
      $route->setDefault('_controller', '\Drupal\sitenow\Controller\Custom403Controller::build');
      $route->setDefault('_title_callback', '\Drupal\sitenow\Controller\Custom403Controller::title');
    }
    if ($route = $collection->get('system.site_information_settings')) {
      $route->setRequirement('_permission', 'administer basic site settings');
    }
    if ($route = $collection->get('system.site_maintenance_mode')) {
      $route->setRequirement('_permission', 'administer maintenance mode');
    }
  }

}
