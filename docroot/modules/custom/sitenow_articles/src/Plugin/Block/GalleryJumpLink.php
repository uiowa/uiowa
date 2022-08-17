<?php

namespace Drupal\sitenow_articles\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * An image gallery jump link block.
 *
 * @Block(
 *   id = "galleryjumplink_block",
 *   admin_label = @Translation("Gallery Jump Link Block"),
 *   category = @Translation("Restricted")
 * )
 */
class GalleryJumpLink extends BlockBase {

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
    $markup = '<span class="fas fa-image"></span> <a href="#gallery">Image Gallery</a>';

    return [
      '#markup' => $markup,
      '#attributes' => [
        'class' => [
          'gallery-jump-link',
        ],
      ],
      '#attached' => [
        'library' => [
          'sitenow_articles/gallery-jump-link',
        ],
      ],
    ];
  }

}
