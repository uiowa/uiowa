<?php

namespace Drupal\uiowa_events;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A Content Hub API client interface.
 */
interface ContentHubApiClientInterface extends ApiClientInterface {

  /**
   * Get all buildings.
   *
   * @return \stdClass[]|bool
   *   The events object.
   */
  public function getEvents(): array|bool;

}
