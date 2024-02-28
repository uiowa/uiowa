<?php

namespace Drupal\facilities_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * A Services headline block.
 *
 * @Block(
 *   id = "services_headline_block",
 *   admin_label = @Translation("Services Headline Block"),
 *   category = @Translation("Restricted")
 * )
 */
class ServicesHeadline extends BlockBase {

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
    $markup = '<h2 class="headline headline--serif headline--underline h5">Building Services</h2>';

    return ['#markup' => $markup];
  }

}
