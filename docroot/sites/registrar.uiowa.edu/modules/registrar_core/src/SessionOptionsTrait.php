<?php

namespace Drupal\registrar_core;

use Drupal\uiowa_maui\MauiApi;

/**
 * Provides session options.
 */
trait SessionOptionsTrait {
  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * Sets the MAUI API service.
   *
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API service.
   */
  public function setMauiApi(MauiApi $maui) {
    $this->maui = $maui;
  }

  /**
   * Helper function to generate select list options for sessions.
   *
   * @param int $previous
   *   How many sessions to go backwards.
   * @param int $future
   *   How many sessions to go forwards.
   * @param bool $legacy
   *   Whether the id or legacyCode key should be used.
   *
   * @return array
   *   Array of select list options.
   */
  public function getSessionOptions(int $previous = 4, int $future = 4, bool $legacy = TRUE): array {
    if (!isset($this->maui)) {
      $this->maui = \Drupal::service('uiowa_maui.api');
    }

    $sessions = $this->maui->getSessionsBounded($previous, $future);
    $options = [];

    $key = ($legacy) ? 'legacyCode' : 'id';

    foreach ($sessions as $session) {
      $options[$session->$key] = $session->shortDescription;
    }

    return $options;
  }

}
