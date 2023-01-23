<?php

namespace Drupal\uiowa_core\Element;

use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Template\Attribute;

/**
 * Provides a render element to display a card.
 *
 * @RenderElement("card")
 */
class Card extends RenderElement {

  /**
   * {@inheritdoc}
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
      '#attributes' => [],
      '#media' => NULL,
      '#media_attributes' => [],
      '#title' => NULL,
      '#title_heading_size' => 'h2',
      '#subtitle' => NULL,
      '#meta' => [],
      '#content' => NULL,
      '#url' => NULL,
      '#link_text' => NULL,
      '#link_indicator' => FALSE,
      '#linked_element' => FALSE,
      '#aria_describedby' => '',
    ];
  }

  /**
   * Pre-render callback: Renders a card into #markup.
   */
  public static function preRenderCard($element) {
    // Add standard card classes.
    if (!isset($element['#attributes'])) {
      $element['#attributes'] = new Attribute();
    }
    elseif (!$element['#attributes'] instanceof Attribute) {
      $element['#attributes'] = new Attribute($element['#attributes']);
    }
    $element['#attributes']->addClass([
      'card',
      'click-container',
      'block--word-break',
    ]);

    return $element;
  }

}
