<?php

namespace Drupal\sitenow_people\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a responsive image element.
 *
 * @RenderElement("image_empty_person")
 */
class ImageEmptyPerson extends RenderElement {

  const RESPONSIVE_STYLE_DEFAULT = 'large__square';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#theme' => 'imagecache_external_responsive',
      '#responsive_image_style_id' => static::RESPONSIVE_STYLE_DEFAULT,
      '#pre_render' => [
        [$class, 'preRenderImageEmptyPerson'],
      ],
    ];
  }

  /**
   * Adds form element theming to details.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   details.
   *
   * @return array
   *   The modified element.
   */
  public static function preRenderImageEmptyPerson(array $element): array {
    $path = \Drupal::service('extension.list.theme')->getPath('uids_base');
    $path = \Drupal::service('file_url_generator')->generateAbsoluteString($path . '/assets/images/person-one.png');
    $element['#uri'] = $path;
    $element['#attributes'] = [
      'data-lazy' => TRUE,
      'alt' => t('@title', [
        '@title' => $element['#alt'] ?? 'No picture provided',
      ]),
    ];

    return $element;
  }

}
