<?php

namespace Drupal\sitenow_dispatch\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for sitenow_dispatch routes.
 */
class SitenowDispatchController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
