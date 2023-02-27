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
      '#attached' => [
        'library' => [
          'uids_base/card',
        ],
      ],
      '#theme' => 'card',
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
    // Prevent processing multiple times.
    if (!isset($element['#processed']) || !$element['#processed']) {
      // Add standard card classes.
      if (!isset($element['#attributes'])) {
        $element['#attributes'] = new Attribute();
      }
      elseif (!$element['#attributes'] instanceof Attribute) {
        $element['#attributes'] = new Attribute($element['#attributes']);
      }
      $element['#attributes']->addClass([
        'block--word-break',
      ]);
    }

    return $element;
  }

  /**
   * Filters a list of styles to just those used by cards.
   *
   * @param array $styles
   *   The styles being filtered.
   *
   * @return array
   *   The filtered styles.
   */
  public static function filterCardStyles(array $styles): array {
    $filtered_styles = [];
    foreach ($styles as $key => $style) {
      foreach ([
        'bg',
        'card',
        'media',
        'borderless',
      ] as $check) {
        if (str_starts_with($style, $check)) {
          $filtered_styles[$key] = $style;
        }
      }
    }

    return $filtered_styles;
  }

}
