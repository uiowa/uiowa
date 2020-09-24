<?php

namespace Drupal\layout_builder_custom;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\layout_builder_styles\Entity\LayoutBuilderStyle;

/**
 * Layout builder pre-render class.
 *
 * Adds layout builder style background class to
 * the 'layout-builder__section' container.
 */
class LayoutBuilderPreRender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Pre-render callback for Layout Builder.
   */
  public static function preRender($element) {
    $lb = &$element['layout_builder'];

    // Loop through each section.
    foreach (Element::children($lb) as $section) {
      if (isset($lb[$section]['layout-builder__section'])
        && isset($lb[$section]['layout-builder__section']['#settings'])
        && isset($lb[$section]['layout-builder__section']['#settings']['layout_builder_styles_style'])
        && is_array($lb[$section]['layout-builder__section']['#settings']['layout_builder_styles_style'])
      ) {
        foreach ($lb[$section]['layout-builder__section']['#settings']['layout_builder_styles_style'] as $style_name) {
          $check = 'section_background';
          if (substr($style_name, 0, strlen($check)) === $check) {
            // @todo Get style name
            $style = LayoutBuilderStyle::load($style_name);
            if (!is_null($style)) {
              $classes = $style->getClasses();
              $lb[$section]['#attributes']['class'][] = $classes;
            }
          }
        }
      }
    }

    return $element;
  }

}
