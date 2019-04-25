<?php

namespace Drupal\uiowa_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for University of Iowa Events Single routes.
 */
class UIEventsController extends ControllerBase {

  /**
   * Builds the response.
   *
   * @param string $event_id
   *   The id of the event.
   *
   * @return array
   *   A renderable array for single event.
   */
  public function build($event_id) {
    // If the configuration is to link out, make all event pages 404.
    $uiowa_events_config = \Drupal::config('uiowa_events.settings');
    if ($uiowa_events_config->get('uiowa_events.event_link') == 'event-link-external') {
      throw new NotFoundHttpException();
    }
    else {
      $events = uiowa_events_load([], ['node', $event_id . '.json']);

      $build = [
        '#theme' => 'uiowa_events_single_event',
        '#data' => $events['events'],
        '#title' => $events['events'][0]['title'],
      ];

    }
    return $build;
  }

}
