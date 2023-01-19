<?php

namespace Drupal\sitenow\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * A 'Powered by SiteNow' block.
 *
 * This is really to establish 'Custom' category for config management purposes.
 *
 * @Block(
 *   id = "sitenow_block",
 *   admin_label = @Translation("Today's Schedule"),
 *   category = @Translation("Site custom")
 * )
 */
class SiteNowBlock extends BlockBase {

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
    return [
      '#markup' =>
      '<span>' . $this->t("Today's Schedule") . '</span>',
    ];
  }

}
