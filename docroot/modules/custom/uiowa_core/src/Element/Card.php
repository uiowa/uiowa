<?php

namespace Drupal\uiowa_core\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Template\Attribute;

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
    // Add standard card classes.
    foreach (['card', 'click-container', 'block--word-break'] as $class) {
      $element['#attributes']['class'][] = $class;
    }

    // Add link indicator classes if relevant.
    if ($element['#link_indicator']) {
      $element['##attributes']['class'][] = 'bttn--circle';
      $element['##attributes']['class'][] = 'bttn--no-text';
    }

    // Create a set of media classes in case its needed.
    $media_classes = ['media'];

    // Loop through all classes, add any media classes to the array and remove
    // them from the card classes.
    foreach ($element['#attributes']['class'] as $index => $style) {
      if (str_starts_with($style, 'media')) {
        $media_classes[] = $style;
        unset($element['#attributes']['class'][$index]);
      }
    }

    // If there is a media element, add the media library and classes.
    // @todo Update this Element:isEmpty() in
    //   https://github.com/uiowa/uiowa/issues/6061.
    if (isset($element['#media']) && count(Element::children($element['#media'])) > 0) {
      $element['#attached']['library'][] = 'uids_base/media';
      $element['#media_attributes'] = new Attribute();

      $element['#media_attributes']->addClass($media_classes);
    }

    return $element;
  }

}
