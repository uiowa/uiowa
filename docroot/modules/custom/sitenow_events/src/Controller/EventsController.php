<?php

namespace Drupal\sitenow_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for single event routes.
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
   *   A render array for single event.
   */
  public function build($event_id, $event_instance) {
    // If the configuration is to link out, make all event pages 404.
    if ($this->config('sitenow_events.settings')->get('event_link') == 'event-link-external') {
      throw new NotFoundHttpException();
    }
    else {
      $events = sitenow_events_load([], ['node', $event_id . '.json']);

      if (!isset($events['events'], $events['events'][0], $events['events'][0]['event_instances'], $events['events'][0]['event_instances'][$event_instance])) {
        throw new NotFoundHttpException();
      }
      else {
        if ($events['events'][0]['canceled'] == TRUE) {
          $title = '[CANCELED] ' . $events['events'][0]['title'];
        }
        else {
          $title = $events['events'][0]['title'];
        }

        return [
          '#theme' => 'sitenow_events_single_event',
          '#data' => $events['events'],
          '#title' => $title,
        ];
      }
    }
  }

}
