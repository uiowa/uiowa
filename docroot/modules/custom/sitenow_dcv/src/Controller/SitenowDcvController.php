<?php

namespace Drupal\sitenow_dcv\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for DCV routes.
 */
class SitenowDcvController extends ControllerBase {

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SitenowDcvController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns file content.
   */
  public function dcvFile($filename) {
    /** @var \Drupal\file\Entity\File[] $file */
    $file = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties(['filename' => $filename]);

    // There can be only one (file replaced on upload).
    $file = array_pop($file);

    if ($file) {
      return new BinaryFileResponse($file->getFileUri(), 200, ['Content-Type' => 'text/plain']);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
