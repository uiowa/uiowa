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
    $uiowa_core_helpers = \Drupal::service('uiowa_core.helpers');
    $is_admin = $uiowa_core_helpers->isAdmin(\Drupal::currentUser());
    // Change form for the system.site_information_settings route
    // to Drupal\describe_site\Form\DescribeSiteSiteInformationForm
    // First, we need to act only on the system.site_information_settings route.
    if ($route = $collection->get('system.site_information_settings')) {
      // Next, we need to set the value for _form to the form we have created.
      $route->setDefault('_form', 'Drupal\uiowa_core\Form\UiowaCoreSiteInformationForm');
    }
    // Block route for non-admins.
    if ($route = $collection->get('google_tag.settings_form')) {
      if (!$is_admin) {
        $route->setRequirement('_access', 'FALSE');
      }
    }
  }

}
