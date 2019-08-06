<?php

namespace Drupal\sitenow_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for University of Iowa Events Single routes.
 */
class EventsController extends ControllerBase {

  /**
   * Builds the response.
   *
   * @param int $event_id
   *   The ID of the event.
   * @param int $event_instance
   *   The ID of the event instance.
   *
   * @return array
   *   A renderable array for single event.
   */
  public function build($event_id, $event_instance) {
    // If the configuration is to link out, make all event pages 404.
    $sitenow_events_config = \Drupal::config('sitenow_events.settings');
    if ($sitenow_events_config->get('sitenow_events.event_link') == 'event-link-external') {
      throw new NotFoundHttpException();
    }
    else {
      $events = sitenow_events_load([], ['node', $event_id . '.json']);

      if (!isset($events['events'], $events['events'][0], $events['events'][0]['event_instances'], $events['events'][0]['event_instances'][$event_instance])) {
        throw new NotFoundHttpException();
      }
      else {
        $build = [
          '#theme' => 'sitenow_events_single_event',
          '#data' => $events['events'],
          '#title' => $events['events'][0]['title'],
        ];

        return $build;
      }
    }
  }

}
