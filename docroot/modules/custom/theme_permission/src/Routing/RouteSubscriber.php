<?php

declare(strict_types=1);

namespace Drupal\theme_permission\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD)
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('block.admin_display_theme')) {
      $route->setRequirement('_custom_access', '\Drupal\theme_permission\Controller\AccessController::access');
    }
    if ($route = $collection->get('system.theme_settings_theme')) {
      $route->setRequirement('_custom_access', '\Drupal\theme_permission\Controller\AccessController::access');
    }
    if ($route = $collection->get('system.theme_set_default')) {
      $route->setRequirement('_custom_access', '\Drupal\theme_permission\Controller\AccessController::access');
    }
    if ($route = $collection->get('system.theme_install')) {
      $route->setRequirement('_custom_access', '\Drupal\theme_permission\Controller\AccessController::access');
    }
    if ($route = $collection->get('system.theme_uninstall')) {
      $route->setRequirement('_custom_access', '\Drupal\theme_permission\Controller\AccessController::access');
    }
    if ($route = $collection->get('system.themes_page')) {
      $route->setDefaults(['_controller' => '\Drupal\theme_permission\Controller\AccessController::themesPage']);
    }

  }

}
