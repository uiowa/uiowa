<?php

namespace Drupal\transportation_calculator\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "transportation_cost_calculator",
 *   admin_label = @Translation("Cost Calculator"),
 *   category = @Translation("transportation")
 * )
 */
class CostCalculatorBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
