<?php

namespace Drupal\layout_builder_custom;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\layout_builder_styles\Entity\LayoutBuilderStyle;

class LayoutBuilderPreRender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * #pre_render callback: Alters layout builder to use dropbuttons to add custom blocks.
   */
  public static function preRender($element) {
    $lb = &$element['layout_builder'];

    // Loop through each section.
    foreach (Element::children($lb) as $section) {
      if (isset($lb[$section]['layout-builder__section'])
        && isset($lb[$section]['layout-builder__section']['#settings'])
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
