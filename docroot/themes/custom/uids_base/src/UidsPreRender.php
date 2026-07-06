<?php

namespace Drupal\uids_base;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Implements trusted prerender callbacks for the UIDS Base theme.
 *
 * @internal
 */
class UidsPreRender implements TrustedCallbackInterface {

  /**
   * Prerender callback for status_messages placeholder.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   The updated renderable array containing the placeholder.
   */
  public static function messagePlaceholder(array $element) {
    // Set up the fallback placeholder with UIDS-specific attributes.
    if (isset($element['fallback']['#markup'])) {
      $element['fallback']['#markup'] = '<div data-drupal-messages-fallback class="hidden messages-list uids-messages-container"></div>';
    }
    $element['#attached']['library'][] = 'uids_base/status-messages';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'messagePlaceholder',
    ];
  }

}
