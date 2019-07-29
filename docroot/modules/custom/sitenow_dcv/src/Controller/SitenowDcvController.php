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
    /* @var \Drupal\file\Entity\File[] $file */
    $file = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties(['filename' => $filename]);

    // There can only be one file with this name since we are replacing on upload.
    $file = array_pop($file);

    if ($file) {
      return new BinaryFileResponse($file->getFileUri(), 200, ['Content-Type' => 'text/plain']);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
