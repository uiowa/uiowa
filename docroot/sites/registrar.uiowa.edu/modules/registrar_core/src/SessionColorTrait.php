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
  protected $session_colors = ['primary', 'success', 'info', 'warning', 'danger'];

  /**
   * Gets the color for a given session ID.
   *
   * @param int $session_id
   *   The ID of the session.
   *
   * @return string
   *   The color class for the session.
   */
  protected function getSessionColor($session_id) {
    return $this->session_colors[$session_id % count($this->session_colors)];
  }

  /**
   * Gets all available colors.
   *
   * @return array
   *   An array of all color classes.
   */
  protected function getAllSessionColors() {
    return $this->session_colors;
  }

}
