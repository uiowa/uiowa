<?php

namespace Drupal\uiowa_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class UiowaCoreRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Change form for the system.site_information_settings route
    // to Drupal\describe_site\Form\DescribeSiteSiteInformationForm
    // First, we need to act only on the system.site_information_settings route.
    if ($route = $collection->get('system.site_information_settings')) {
      // Next, we need to set the value for _form to the form we have created.
      $route->setDefault('_form', 'Drupal\uiowa_core\Form\UiowaCoreSiteInformationForm');
    }
    $restricted_routes = [
      // Google Tag Manager settings.
      'google_tag.settings_form',
      // Global theme settings.
      'system.theme_settings',
    ];

    // Restrict access to these routes for non-admins.
    foreach ($restricted_routes as $restricted_route) {
      if ($route = $collection->get($restricted_route)) {
        $route->setRequirement('_uiowa_core_access_check', 'TRUE');
      }
    }
  }

}
