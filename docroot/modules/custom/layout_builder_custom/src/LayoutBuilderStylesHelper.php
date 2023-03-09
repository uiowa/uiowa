<?php

namespace Drupal\layout_builder_custom;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;

/**
 * Helper class for doing common processing related to Layout Builder styles.
 */
class LayoutBuilderStylesHelper {

  /**
   * Helper method to provide a key-value map of styles for list blocks.
   *
   * @param array $styles
   *   The styles to provide a map for.
   *
   * @return array
   *   The style map.
   */
  public static function getLayoutBuilderStylesMap(array $styles): array {
    $style_map = [];
    try {
      // Account for incorrectly configured component configuration which may
      // have a NULL style ID by filtering the array. We cannot pass NULL to the
      // storage handler, or it will throw an exception.
      /** @var \Drupal\layout_builder_styles\LayoutBuilderStyleInterface[] $styles */
      $styles = \Drupal::entityTypeManager()
        ?->getStorage('layout_builder_style')
        ?->loadMultiple(array_filter($styles));

      foreach ($styles as $style) {
        $classes = implode(' ', \preg_split('(\r\n|\r|\n)', $style->getClasses()));

        // Remove grid classes if list format is set.
        if (str_starts_with($classes, 'grid--') && isset($styles['list_format_list'])) {
          continue;
        }

        if (empty($style_map[$style->getGroup()])) {
          $style_map[$style->getGroup()] = $classes;
        }
        else {
          $style_map[$style->getGroup()] .= " $classes";
        }
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // I don't think we do anything here except not add the style.
    }

    return $style_map;
  }

  /**
   * Unset classes in a style map from an attributes array.
   *
   * @param array $attributes
   *   The attributes array to be processed.
   * @param array $style_map
   *   A style map of Layout Builder styles.
   */
  public static function removeStylesFromAttributes(array &$attributes, array $style_map) {
    // Filter class list to only elements didn't match a style from the style
    // map.
    $attributes['class'] = array_filter($attributes['class'], function ($class) use ($style_map) {
      foreach ($style_map as $style) {
        if (str_contains($style, $class)) {
          return FALSE;
        }
      }
      return TRUE;
    });
  }

  /**
   * Filters a style map to remove any that match a list of prefixes.
   *
   * @param array $style_map
   *   A style map of Layout Builder styles.
   * @param array $removal_list
   *   The list prefixes to filter out.
   *
   * @return array
   *   The filtered styles.
   */
  public static function filterStyles(array $style_map, array $removal_list = []): array {
    $filtered_styles = [];
    foreach ($style_map as $key => $style) {
      foreach ($removal_list as $check) {
        if (str_starts_with($style, $check)) {
          $filtered_styles[$key] = $style;
        }
      }
    }

    return $filtered_styles;
  }

}
