<?php

namespace Drupal\sitenow_landing_pages;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Replaces links in rendered markup with spans, keeping their contents.
 */
class LandingPageUnlink implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['postRender'];
  }

  /**
   * Post-render callback that replaces each link with a styling span.
   */
  public static function postRender($markup, array $element) {
    $dom = Html::load((string) $markup);

    // Copy the node list first, since replacing links changes the DOM.
    foreach (iterator_to_array($dom->getElementsByTagName('a')) as $link) {
      $span = $dom->createElement('span');
      $span->setAttribute('class', 'landing-page-unlinked');

      while ($link->firstChild) {
        $span->appendChild($link->firstChild);
      }
      $link->parentNode->replaceChild($span, $link);
    }

    return Markup::create(Html::serialize($dom));
  }

}
