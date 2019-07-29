<?php

namespace Drupal\sitenow_events\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines a dynamic path based off of the redirect uri variable.
 */
class EventsRoutes {

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {
    $routes = [];

    $path = \Drupal::config('sitenow_events.settings')->get('sitenow_events.single_event_path')?: 'event';

    $routes['sitenow_events.single_controller.' . $path] = new Route(
      $path . '/{event_id}/{event_instance}',
      [
        '_controller' => '\Drupal\sitenow_events\Controller\EventsController::build',
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
