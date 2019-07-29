<?php

namespace Drupal\sitenow_dcv\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for DCV routes.
 */
class SitenowDcvController extends ControllerBase {

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
