<?php

namespace Drupal\uiowa_core\Commands;

use Consolidation\AnnotatedCommand\CommandData;

trait CpuTimeTrait {

  protected array $instrument = [];

  /**
   * Initializes the measurements.
   *
   * @return void
   */
  public function initMeasurement()
  {
    $this->instrument['times'] = [microtime(true)];
  }

  /**
   * Collects results and prints them.
   *
   * @return void
   */
  public function finishMeasurment()
  {
    $this->instrument['times'][] = microtime(true);
    printf("Duration: %5.2f seconds\n", $this->instrument['times'][1] - $this->instrument['times'][0]);
  }
}
