<?php

namespace Drupal\sitenow_signage;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A Content Hub API client interface.
 */
interface MazevoApiClientInterface extends ApiClientInterface {

  /**
   * Get all buildings.
   *
   * @return \stdClass|bool
   *   The events object.
   */
  public function getEvents(): \stdClass|bool;

}
