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
      $event = sitenow_events_load([], ['node', "{$event_id}.json"]);

      if (!isset($event['event_instances'], $event['event_instances'][$event_instance])) {
        throw new NotFoundHttpException();
      }
      else {
        return [
          '#theme' => 'sitenow_events_single_event',
          '#event' => $event,
          '#cache' => [
            'tags' => ['time:hourly'],
            'max-age' => 60,
          ],
        ];
      }
    }
  }

  /**
   * Single event page title callback.
   *
   * @param int $event_id
   *   The ID of the event.
   *
   * @return string
   *   The event title.
   */
  public function title($event_id) {
    $title = '';
    $event = $this->getEventData($event_id);

    if (isset($event['title'])) {
      $title = $event['title'];
    }

    return $title;
  }

  /**
   * Get event data from the API.
   */
  protected function getEventData($event_id) {
    return sitenow_events_load([], ['node', "{$event_id}.json"]);
  }

}
