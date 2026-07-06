<?php

namespace Drupal\safety_core\Plugin\Block;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides a Fire Log block.
 *
 * @Block(
 *   id = "fire_log_block",
 *   admin_label = @Translation("Fire log"),
 *   category = @Translation("Site custom")
 * )
 */
class FireLogBlock extends LogBlock {

  /**
   * {@inheritdoc}
   */
  public function getLogType() {
    return 'fire';
  }

  /**
   * {@inheritdoc}
   */
  public function getDataKey() {
    return 'fires';
  }

  /**
   * {@inheritdoc}
   */
  public function getCountKey() {
    return 'fireCount';
  }

  /**
   * {@inheritdoc}
   */
  public function getLogData($start_date, $end_date, $limit = NULL) {
    return $this->cleryController->getFireData($start_date, $end_date, $limit);
  }

  /**
   * Gets the default date range for fire logs.
   *
   * @return array
   *   Array with 'start' and 'end' keys containing formatted dates.
   */
  public function getDefaultDateRange(): array {
    return [
      'start' => (new DrupalDateTime('60 days ago'))->format('Y-m-d'),
      'end' => (new DrupalDateTime('today'))->format('Y-m-d'),
    ];
  }

}
