<?php

namespace Drupal\facilities_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * A Map block.
 *
 * @Block(
 *   id = "map_block",
 *   admin_label = @Translation("Map Block"),
 *   category = @Translation("Site custom")
 * )
 */
class MapBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $markup = '<div>Hello World!</div>';
    return ['#markup' => $markup];
  }

}
