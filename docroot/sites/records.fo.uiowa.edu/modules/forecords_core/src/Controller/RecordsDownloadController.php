<?php

namespace Drupal\forecords_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Downloads a stored copy of records.
 */
class RecordsDownloadController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file_system service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(FileSystemInterface $fileSystem, MessengerInterface $messenger) {
    $this->fileSystem = $fileSystem;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('messenger')
    );
  }

  /**
   * Download today's version of records.
   */
  public function downloadRecordsExport() {
    $file_path = 'public://exports/records_' . date('Y-m-d') . '.csv';
    $real_path = $this->fileSystem->realpath($file_path);

    if (file_exists($real_path)) {
      $response = new BinaryFileResponse($real_path);
      $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($real_path));
      $response->headers->set('Content-Type', 'text/csv');
      return $response;
    }

    throw new NotFoundHttpException();
  }

}
