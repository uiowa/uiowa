<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\FileCopy;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\CreateMediaTrait;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;

/**
 * Generates a media entity if one doesn't already exist.
 *
 * @MigrateProcessPlugin(
 *   id = "create_media_from_file_field"
 * )
 */
class CreateMediaFromFile extends FileCopy {
  use CreateMediaTrait;

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, StreamWrapperManagerInterface $stream_wrappers, FileSystemInterface $file_system, MigrateProcessInterface $download_plugin) {
    $this->entityTypeManager = \Drupal::entityTypeManager();
    parent::__construct($configuration, $plugin_id, $plugin_definition, $stream_wrappers, $file_system, $download_plugin);
  }

  /**
   * {@inheritdoc}
   *
   * value = [
   *   fid,
   *   alt,
   *   title,
   *   width,
   *   height,
   * ]
   *
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    // @todo Check if file already exists and get file ID.
    // @todo Create file is necessqary and get file ID.
    return parent::transform($value, $migrate_executable, $row, $destination_property);

  }

  /**
   * Process a file field.
   *
   * @param int $fid
   *   The file ID.
   * @param array $meta
   *   Metadata for the file.
   *
   * @return int|null
   *   The media ID or null if unable to process.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\migrate\MigrateException
   */
  protected function processFileField($fid, array $meta = []) {
    $fileQuery = $this->fidQuery($fid);

    $filename_w_subdir = str_replace('public://', '', $fileQuery['uri']);
    $fileQuery = NULL;

    // Split apart the filename from the subdirectory path.
    $filename_w_subdir = explode('/', $filename_w_subdir);
    $filename = array_pop($filename_w_subdir);
    $subdir = implode('/', $filename_w_subdir) . '/';
    $filename_w_subdir = NULL;

    // Get a connection for the destination database
    // and retrieve the associated fid.
    $new_fid = \Drupal::database()->select('file_managed', 'f')
      ->fields('f', ['fid'])
      ->condition('f.filename', $filename)
      ->execute()
      ->fetchField();

    // If there's no fid in the D8 database,
    // then we'll need to fetch it from the source.
    if (!$new_fid) {
      // Use the filename, update the source base path with the subdirectory.
      $new_fid = $this->downloadFile($filename, $this->getSourcePublicFilesUrl() . $subdir, $this->getDrupalFileDirectory() . $subdir);
      $subdir = NULL;

      if ($new_fid) {
        $mid = $this->createMediaEntity($new_fid, $meta, 1);
      }
    }
    else {
      $mid = $this->getMid($filename)['mid'];
      $filename = NULL;

      // And in case we had the file, but not the media entity.
      if (!$mid) {
        $mid = $this->createMediaEntity($new_fid, $meta, 1);
        $meta = NULL;
      }
    }

    return $mid ?? NULL;
  }

  public function getMidByFilename($filename, $type = 'image') {
    // Array of Medias witch contains your file.
    // Load file by filename
    // array.
    $ids = $this->entityTypeManager
      ->getStorage('file')
      ->getQuery()
      ->condition('filename', $filename)
      ->execute();

    // Get First file (make a loop if you get many files)
    $fileId = array_shift($file)->fid->value;

    // Array of Medias witch contains your file.
    $this->entityTypeManager
      ->getStorage('media')
      ->loadByProperties(['field_media_image' => $fileId]);
  }

}
