<?php

namespace Drupal\sitenow_signage;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A Mazevo API client interface.
 */
interface MazevoApiClientInterface extends ApiClientInterface {

  /**
   * Get events.
   *
   * @return \stdClass|bool
   *   The events object.
   */
  public function getEvents(): \stdClass|bool;

}
