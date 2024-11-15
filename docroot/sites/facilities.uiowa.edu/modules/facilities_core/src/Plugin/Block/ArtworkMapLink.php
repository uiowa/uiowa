<?php

namespace Drupal\facilities_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * An Apply button block.
 *
 * @Block(
 *   id = "artworkmaplink_block",
 *   admin_label = @Translation("Artwork Map Link"),
 *   category = @Translation("Site custom")
 * )
 */
class ArtworkMapLink extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $attributes = [];
    $attributes['class'] = [
      'card--layout-left',
      'borderless',
      'media--circle',
      'media--border',
      'headline--serif',
      'media--medium',
    ];

    $build['container']['schedule'][] = [
      '#type' => 'card',
      '#attributes' => $attributes,
      '#url' => 'https://uiadmin.maps.arcgis.com/apps/webappviewer/index.html?id=c4bcf56619a241deb3e8f490ce1b9ed6',
      '#subtitle' => 'Explore locations of artwork across the campus. ',
      '#title' => 'Art on Campus Map',
      '#media' => [
        '#theme' => 'image',
        '#uri' => '/themes/custom/uids_base/assets/images/brochure-two-color-square.png',
        '#alt' => 'Map Icon',
      ],
    ];

    return $build;

  }

}
