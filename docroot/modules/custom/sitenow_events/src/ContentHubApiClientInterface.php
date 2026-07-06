<?php

namespace Drupal\sitenow_events;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A Content Hub API client interface.
 */
interface ContentHubApiClientInterface extends ApiClientInterface {

  /**
   * Get all events.
   *
   * @return \stdClass|bool
   *   The events object.
   */
  public function getEvents(): \stdClass|bool;

  /**
   * Get all events with instances.
   *
   * @return array|bool
   *   The array of event objects.
   */
  public function getEventInstances(): array|bool;

}
