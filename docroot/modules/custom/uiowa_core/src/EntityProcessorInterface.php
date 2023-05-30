<?php

namespace Drupal\uiowa_core;

/**
 * Entity sync operation.
 */
interface EntityProcessorInterface {

  /**
   * Run the process operation.
   */
  public function process();

}
