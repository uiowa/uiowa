<?php

namespace Drupal\uiowa_core\Element;

use Drupal\Component\Utility\Html;
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
    // Set up various attributes if not already set up.
    foreach ([
      'attributes',
      'button_attributes',
      'media_attributes',
    ] as $attributes_type) {
      if (!isset($element["#$attributes_type"])) {
        $element["#$attributes_type"] = new Attribute();
      }
      elseif (!$element["#$attributes_type"] instanceof Attribute) {
        $element["#$attributes_type"] = new Attribute($element["#$attributes_type"]);
      }
    }

    // Create a set of title headline classes in case its needed.
    $headline_classes = ['headline'];

    // Loop through all classes, add any media and headline classes to the array and remove
    // them from the card classes.
    if (isset($element['#attributes']['class'])) {
      foreach ($element['#attributes']['class'] as $style) {
        if (str_starts_with($style, 'media')) {
          $element['#media_attributes']->addClass($style);
          $element['#attributes']->removeClass($style);
        }
        if (str_starts_with($style, 'headline')) {
          $headline_classes[] = $style;
          $element['#attributes']->removeClass($style);
        }
      }
    }

    // If there is a media element, add the media library.
    if (isset($element['#media'])) {
      $element['#attached']['library'][] = 'uids_base/media';
    }

    $linked_element = FALSE;

    // If there is no URL, then it is not linked.
    if (!empty($element['#url'])) {

      // Determine the linked element.
      if (!isset($element['#link_text']) && $element['#link_indicator']) {
        $linked_element = 'button';
      }
      elseif (!is_null($element['#title'])) {
        $linked_element = 'title';
      }
      elseif (isset($element['#link_text'])) {
        $linked_element = 'button';
      }
      elseif (!empty($element['#media'])) {
        $linked_element = 'media';
      }
    }

    $element['#linked_element'] = $linked_element;

    if (!empty($element['#title'])) {
      $element['#headline'] = [
        'headline_text' => $element['#title'],
        'headline_level' => $element['#title_heading_size'] ?: 'h2',
        'headline_class' => $headline_classes,
      ];

      // @todo Set headline_url based on link being set.
      // Set 'click-target' class on headline URL if title is the linked element.
      if ($element['#linked_element'] === 'title') {
        $element['#headline']['headline_url'] = $element['#url'];
        $element['#headline']['headline_url_class'] = 'click-target';
      }
    }

    $element['#button_attributes'] = new Attribute();

    // Set 'aria-hidden' on the button if it is not linked.
    if ($element['#linked_element'] !== 'button') {
      $element['#button_attributes']->setAttribute('aria-hidden', TRUE);
    }

    // If title and link text are set, set a button id attribute for
    // aria-describedby.
    if (is_string($element['#title']) && !empty($element['#link_text'])) {
      // @todo get this working with paragraphs.
      $aria_id = Html::getUniqueId($element['#title']);
      $element['#headline']['headline_aria'] = $aria_id;
      $element['#button_attributes']->setAttribute('id', $aria_id);
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
