<?php

namespace Drupal\sitenow_articles\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * A preserved links message block.
 *
 * @Block(
 *   id = "preservedlinksmessage_block",
 *   admin_label = @Translation("Preserved Links Message Block"),
 *   category = @Translation("Restricted")
 * )
 */
class PreservedLinksMessage extends BlockBase {

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
    $markup = '<div>Links in this article are preserved for historical purposes, but the destination sources may have changed.</div>';

    return [
      '#markup' => $markup,
      '#attributes' => [
        'class' => [
          'alert alert-info',
        ],
      ],
    ];
  }

}
