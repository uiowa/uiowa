<?php

namespace Drupal\uiowa_covid\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "uiowa_covid_data",
 *   admin_label = @Translation("UIowa COVID Data"),
 *   category = @Translation("uiowa_covid")
 * )
 */
class UiowaCovidData extends BlockBase {

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
