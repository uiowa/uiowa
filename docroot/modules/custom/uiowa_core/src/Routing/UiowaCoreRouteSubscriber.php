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
    // Block route for non-admins for Google Tag Manager settings.
    if ($route = $collection->get('google_tag.settings_form')) {
      $route->setRequirement('_uiowa_core_access_check', 'TRUE');
    }
    // Block route for non-admins for global theme settings.
    if ($route = $collection->get('system.theme_settings')) {
      $route->setRequirement('_uiowa_core_access_check', 'TRUE');
    }
  }

}
