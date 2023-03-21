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
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
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
        'hide',
        'headline',
      ] as $check) {
        if (str_starts_with($style, $check)) {
          $filtered_styles[$key] = $style;
        }
      }
    }

    if (!isset($filtered_styles['border'])) {
      $filtered_styles['border'] = '';
    }

    return $filtered_styles;
  }

}
