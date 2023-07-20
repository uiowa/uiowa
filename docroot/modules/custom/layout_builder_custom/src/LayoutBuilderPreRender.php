<?php

namespace Drupal\layout_builder_custom;

use Drupal\Component\Serialization\Json;
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
      if (isset($lb[$section]['layout-builder__section'])) {
        if (isset($lb[$section]['layout-builder__section']['#settings']['layout_builder_styles_style'])
          && is_array($lb[$section]['layout-builder__section']['#settings']['layout_builder_styles_style'])
        ) {
          // Add background style classes to Layout Builder sections.
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
        foreach (Element::children($lb[$section]['layout-builder__section']) as $region) {
          foreach (Element::children($lb[$section]['layout-builder__section'][$region]) as $uuid) {
            $base_plugin_id = $lb[$section]['layout-builder__section'][$region][$uuid]['#base_plugin_id'] ?? NULL;
            if ($base_plugin_id) {
              switch ($base_plugin_id) {
                // Force preview of menu block to show placeholder.
                // @todo Remove this once we determine a way to consistently
                //   show the correct rendering of menu blocks in previews.
                case 'menu_block':
                  $derivative_plugin_id = $lb[$section]['layout-builder__section'][$region][$uuid]['#derivative_plugin_id'];
                  $lb[$section]['layout-builder__section'][$region][$uuid]['content'] = [
                    '#markup' => t('Placeholder for the "@menu navigation" block', ['@menu' => ucfirst($derivative_plugin_id)]),
                  ];
                  // Manually add the 'layout-builder-block--placeholder' class
                  // here because it doesn't get added where it should.
                  $lb[$section]['layout-builder__section'][$region][$uuid]['#attributes']['class'][] = 'layout-builder-block--placeholder';
                  break;
              }
            }
          }
        }
      }
    }

    return $element;
  }

}
