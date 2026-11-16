<?php

namespace Drupal\sitenow_dcv\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for DCV routes.
 */
class SitenowDcvController extends ControllerBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * SitenowDcvController constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * Returns the uploaded DCV file.
   */
  public function dcvFile($filename) {
    $uri = 'public://.well-known/pki-validation/' . $filename;

    if ($filename !== $this->state->get('sitenow_dcv_filename') || !file_exists($uri)) {
      throw new NotFoundHttpException();
    }

    return new BinaryFileResponse($uri, 200, ['Content-Type' => 'text/plain']);
  }

}
