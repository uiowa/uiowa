<?php

namespace Drupal\uiowa_dcv\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Returns responses for Uiowa DCV routes.
 */
class UiowaDcvController extends ControllerBase {

  /**
   * Returns file content.
   */
  public function dcvFile($filename) {
    if (file_exists("public://dcv/{$filename}")) {
      return new BinaryFileResponse("public://dcv/{$filename}", 200, ['Content-Type' => 'text/plain']);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
