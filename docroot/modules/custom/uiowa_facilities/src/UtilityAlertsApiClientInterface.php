<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A Utility Alerts API client interface.
 */
interface UtilityAlertsApiClientInterface extends ApiClientInterface {

  /**
   * Get utility alerts.
   *
   * @param int $days
   *   The number of days of alerts to retrieve.
   *
   * @return array|false
   *   The alerts data or FALSE on failure.
   */
  public function getAlerts(int $days = 14): array|false;

}
