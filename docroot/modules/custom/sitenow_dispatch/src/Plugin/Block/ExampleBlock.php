<?php

namespace Drupal\sitenow_dispatch\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "sitenow_dispatch_example",
 *   admin_label = @Translation("Example"),
 *   category = @Translation("sitenow_dispatch")
 * )
 */
class ExampleBlock extends BlockBase {

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
