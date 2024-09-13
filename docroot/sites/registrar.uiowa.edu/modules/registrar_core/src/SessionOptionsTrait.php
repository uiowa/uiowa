<?php

namespace Drupal\registrar_core;

use Drupal\Core\Cache\Cache;
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
    // Define a unique cache ID based on method parameters.
    $cid = 'registrar_core:session_options:' . $previous . ':' . $future . ':' . ($legacy ? 'legacy' : 'id');

    // Get the cache backend.
    $cache = \Drupal::cache();

    // Try to load the data from cache.
    if ($cache_data = $cache->get($cid)) {
      return $cache_data->data;
    }

    // Fetch the page cache maximum age from the configuration.
    $config = \Drupal::config('system.performance');
    $max_age = $config->get('cache.page.max_age');
    $request_time = \Drupal::time()->getRequestTime();

    // Fetch session data via the MAUI API.
    $sessions = $this->maui->getSessionsBounded($previous, $future);
    $options = [];

    $key = ($legacy) ? 'legacyCode' : 'id';

    foreach ($sessions as $session) {
      $options[$session->$key] = $session->shortDescription;
    }

    // Cache the result using the page cache maximum age setting.
    $expiration_time = $max_age > 0 ? $request_time + $max_age : Cache::PERMANENT;
    $cache->set($cid, $options, $expiration_time, ['maui_api']);

    return $options;
  }

}
