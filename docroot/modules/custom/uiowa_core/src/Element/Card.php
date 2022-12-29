<?php

namespace Drupal\uiowa_core\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a render element to display a card.
 *
 * @RenderElement("card")
 */
class Card extends RenderElement {

  /**
   * @inheritDoc
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#pre_render' => [
        [$class, 'preRenderCard'],
      ],
      '#theme' => 'card',
      '#attached' => [
        'library' => [
          'uids_base/card',
        ],
      ],
      '#media' => NULL,
      '#title' => NULL,
      '#subtitle' => NULL,
      '#meta' => [],
      '#content' => NULL,
      '#linked_element' => FALSE,
      '#url' => NULL,
      '#link_text' => NULL,
      '#link_indicator' => FALSE,
    ];
  }

  public static function preRenderCard($element) {
    // If there is a media element, add the media library.
    if (!empty($element['#media'])) {
      $element['#attached']['library'][] = 'uids_base/media';
    }

    foreach (['card', 'click-container', 'block--word-break'] as $class) {
      $element['#attributes']['class'][] = $class;
    }

    // Add link indicator classes if relevant.
    if ($element['#link_indicator']) {
      $element['##attributes']['class'][] = 'bttn--circle';
      $element['##attributes']['class'][] = 'bttn--no-text';
    }

    return $element;
  }

}
