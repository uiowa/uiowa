<?php

namespace Drupal\registrar_core;

/**
 * Provides methods for consistent session color assignment.
 */
trait SessionColorTrait {

  /**
   * An array of color classes to be used for sessions.
   *
   * @var array
   */
  protected $sessionColors = ['primary', 'secondary', 'cool-gray', 'blue', 'green', 'orange'];

  /**
   * Gets the color for a given session ID.
   *
   * @param int $sessionId
   *   The ID of the session.
   *
   * @return string
   *   The color class for the session.
   */
  protected function getSessionColor($sessionId) {
    return $this->sessionColors[$sessionId % count($this->sessionColors)];
  }

  /**
   * Gets all available colors.
   *
   * @return array
   *   An array of all color classes.
   */
  protected function getAllSessionColors() {
    return $this->sessionColors;
  }

}
