<?php

namespace Drupal\uiowa_core\Commands;

/**
 * Trait for profiling CPU time usage.
 */
trait CpuTimeTrait {

  /**
   * Array for tracking measurements.
   *
   * @var array
   */
  protected array $instrument = [];

  /**
   * Initializes the measurements.
   */
  public function initMeasurement(): void {
    $this->instrument['times'] = [microtime(TRUE)];
  }

  /**
   * Collects results and prints them.
   */
  public function finishMeasurment(): void {
    $this->instrument['times'][] = microtime(TRUE);
    printf("Duration: %5.2f seconds\n", $this->instrument['times'][1] - $this->instrument['times'][0]);
  }

}
