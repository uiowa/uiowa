<?php

namespace Drupal\uiowa_directory_profiles\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for uiowa_directory_profiles routes.
 */
class UiowaDirectoryProfilesController extends ControllerBase {

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
