<?php

namespace Drupal\layout_builder_custom;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;

/**
 * Helper class for doing common processing related to Layout Builder styles.
 */
class LayoutBuilderStylesHelper {
  public const LIST_PREFIX = 'list-container';

  public const GRID_PREFIX = 'grid';

  /**
   * Helper method to provide a key-value map of styles for list blocks.
   *
   * @param array $styles
   *   The styles to provide a map for.
   *
   * @return string[]
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
   * @return string[]
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

  /**
   * Change the media view mode based on the selected format.
   *
   * @param array $build
   *   The build to adjust.
   * @param string $size
   *   The size of the media view mode.
   * @param string $format
   *   The format of the media view mode.
   */
  public static function setMediaViewModeFromStyle(array &$build, string $size, string $format) {
    $media_formats = [
      'media--circle' => 'square',
      'media--square' => 'square',
      'media--ultrawide' => 'ultrawide',
      'media--widescreen' => 'widescreen',
    ];

    // Loop through map of class to shape to see if we have a match. If so,
    // change the view mode and unset the cache keys to avoid sharing the change
    // with all other instances of the same image.
    foreach ($media_formats as $class => $shape) {
      if (str_starts_with($format, $class)) {
        $view_mode = "{$size}__$shape";
        // Change the view mode to match the format.
        $build['#view_mode'] = $view_mode;
        // Important: Delete the cache keys to prevent this from being
        // applied to all the instances of the same image.
        if (isset($build['#cache']['keys'])) {
          unset($build['#cache']['keys']);
        }
        // Break on the first match.
        break;
      }
    }
  }

  /**
   * Remove grid classes if set as list.
   *
   * @param array $attributes
   *   The attributes array to be processed.
   */
  public static function processGridClasses(array &$attributes) {
    if (in_array('list-container--list', $attributes['class'])) {
      foreach ($attributes['class'] as $key => $style) {
        if (str_starts_with($style, static::GRID_PREFIX)) {
          unset($attributes['class'][$key]);
        }
      }
    }
  }

  /**
   * Return an array of additional settings keyed by group ID.
   *
   * @return array[]
   *   The extra settings.
   */
  public static function getExtraSettings(): array {
    return [
      'background' => [
        'default' => '[empty]',
      ],
      'banner_gradient' => [
        'default' => 'banner_gradient_dark',
      ],
      'banner_height' => [
        'default' => 'banner_medium',
      ],
      'banner_type' => [
        'default' => 'banner_centered_left',
      ],
      'button_size' => [
        'default' => 'button_medium',
      ],
      'button_style' => [
        'default' => 'button_primary',
      ],
      'card_headline_style' => [
        'default' => 'card_headline_style_serif',
      ],
      'card_media_position' => [
        'default' => 'card_media_position_right',
      ],
      'content_alignment' => [
        'default' => '[empty]',
        'empty_label' => t('Left'),
      ],
      'grid_columns' => [
        'default' => 'block_grid_threecol_33_34_33',
      ],
      'headline_type' => [
        'default' => 'headline_bold_serif',
      ],
      'headline_size' => [
        'default' => 'headline_large',
      ],
      'list_format' => [
        'default' => 'list_format_list',
      ],
      'media_format' => [
        'default' => 'media_format_widescreen',
      ],
      'media_size' => [
        'default' => 'media_size_small',
      ],
      'menu_orientation' => [
        'default' => 'block_menu_vertical',
      ],
    ];
  }

}
