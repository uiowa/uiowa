<?php

namespace Drupal\sitenow_alerts\Entity;

/**
 * Defines the interface for closable entities.
 */
interface ClosableInterface {

  /**
   * Determines whether the open window has passed.
   *
   * @return ?bool
   *   Whether the entity should be considered closed.
   */
  public function isClosed(): ?bool;

}
