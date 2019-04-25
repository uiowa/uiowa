<?php

namespace Drupal\uiowa_events\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines a dynamic path based off of the redirect uri variable.
 */
class UIEventsRoutes {

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {
    $routes = [];

    $path = \Drupal::config('uiowa_events.settings')->get('uiowa_events.single_event_path')?: 'event';

    $routes['uiowa_events.single_controller.' . $path] = new Route(
      $path . '/{event_id}/{event_instance}',
      [
        '_controller' => '\Drupal\uiowa_events\Controller\UIEventsController::build',
      ],
      [
        '_permission'  => 'access content',
        'event_id' => '\d+',
        'event_instance' => '\d+',
      ]
    );

    return $routes;
  }

}
