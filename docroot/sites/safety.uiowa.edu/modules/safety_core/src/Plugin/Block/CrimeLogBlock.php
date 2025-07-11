<?php

namespace Drupal\safety_core\Plugin\Block;

/**
 * Provides a Crime Log block.
 *
 * @Block(
 *   id = "crime_log_block",
 *   admin_label = @Translation("Crime log"),
 *   category = @Translation("Site custom")
 * )
 */
class CrimeLogBlock extends LogBlock {

  /**
   * {@inheritdoc}
   */
  protected function getLogType() {
    return 'crime';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDataKey() {
    return 'crimes';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCountKey() {
    return 'crimeCount';
  }

  /**
   * {@inheritdoc}
   */
  protected function getLogData($start_date, $end_date, $limit = NULL) {
    return $this->cleryController->getCrimeData($start_date, $end_date, $limit);
  }

}
