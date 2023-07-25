<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\process;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\Entity\File;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\Plugin\migrate\process\FileCopy;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\CreateMediaTrait;

/**
 * Generates a media entity if one doesn't already exist.
 *
 * Borrowing heavily from
 * https://git.drupalcode.org/project/migrate_file/-/blob/2.1.x/src/Plugin/migrate/process/FileImport.php.
 *
 * @MigrateProcessPlugin(
 *   id = "create_media_from_file_field"
 * )
 */
class CreateMediaFromFile extends FileCopy {
  use CreateMediaTrait;

  /**
   * New file ID.
   *
   * @var string
   */
  protected $newFid;

  /**
   * The base source url.
   *
   * @var string
   */
  protected $sourceBaseUrl;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, StreamWrapperManagerInterface $stream_wrappers, FileSystemInterface $file_system, MigrateProcessInterface $download_plugin) {
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->sourceBaseUrl = \Drupal::config('migrate_plus.migration_group.sitenow_migrate')
      ->get('shared_configuration.source.constants.source_base_path');
    parent::__construct($configuration, $plugin_id, $plugin_definition, $stream_wrappers, $file_system, $download_plugin);
  }

  /**
   * {@inheritdoc}
   *
   * Example value:
   * value = [
   *   fid,
   *   alt,
   *   title,
   *   width,
   *   height,
   *   filename,
   *   uri,
   * ].
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!$value) {
      $migrate_executable->saveMessage('$value does not exist. Skipping.');
      return NULL;
    }
    // Validate that we have the 'uri' property we need to process the file.
    if (!isset($value['uri'])) {
      throw new MigrateException(sprintf('The uri property needs to be included with the process configuration for the %s field.', 'Field'));
    }

    // Grab the filename and subdirectory, and use that to build
    // an absolute url for the source file.
    $filename_w_subdir = str_replace('public://', '', $value['uri']);
    $source = $this->getSourcePublicFilesUrl($row) . $filename_w_subdir;
    // Split apart the filename from the subdirectory path.
    $filename_w_subdir = explode('/', $filename_w_subdir);
    $filename = array_pop($filename_w_subdir);

    // Check if we have a file in the system with this filename.
    // If we do, then return its media id.
    if ($mid = $this->getMidByFilename($filename)) {
      return $mid;
    }

    // Check if there is a file already in the system
    // with the given filename. If so, we just need to create
    // a media entity and return its id.
    if ($fid = $this->getNewFileId($filename)) {
      // Pass in the value as 'meta' for use in the media creation
      // processes, so we can take advantage of properties like
      // human-readable filenames.
      return $this->createMediaEntity($fid, $value);
    }

    // If we didn't match an already downloaded file, and
    // the source doesn't exist, then we have a problem.
    if (!$this->sourceExists($source)) {
      // If we have a source file path, but it doesn't exist, and we're meant
      // to just skip processing, we do so, but we log the message.
      $migrate_executable->saveMessage("Source file {$value['uri']} does not exist. Skipping.");
      return NULL;
    }

    // At this point, if we haven't returned a media id,
    // then we know we need to download the file,
    // create a new file entity,
    // and create its associated media entity.
    // Build the destination file uri (in case only a directory was provided).
    $destination = $this->getDestinationFilePath($filename);
    if (!$this->streamWrapperManager->getScheme($destination)) {
      if (empty($destination)) {
        $destination = \Drupal::config('system.file')->get('default_scheme') . '://' . preg_replace('/^\//', '', $destination);
      }
    }
    $final_destination = '';

    //
    // The parent method will take care of our download/move/copy/rename.
    // We just need the final destination to create the file object.
    //
    try {
      $final_destination = parent::transform([$source, $destination], $migrate_executable, $row, $destination_property);
    }
    catch (MigrateException $e) {
      // Check if we're skipping on error.
      if ($this->configuration['skip_on_error']) {
        $migrate_executable->saveMessage("File $source could not be imported to $destination. Operation failed with message: " . $e->getMessage());
        throw new MigrateSkipProcessException($e->getMessage());
      }
      else {
        // Pass the error back on again.
        throw new MigrateException($e->getMessage());
      }
    }
    // If we're here and we have a final_destination,
    // then we've downloaded a new file, but need to
    // create its file and media entities.
    if ($final_destination) {
      // Create a file entity.
      $file = File::create([
        'uri' => $final_destination,
      ]);
      $file->setPermanent();
      $file->save();
      $this->newFid = $file->id();
      // Pass in the value as 'meta' for use in the media creation
      // processes, so we can take advantage of properties like
      // human-readable filenames.
      $this->newMid = $this->createMediaEntity($this->newFid, $value);

      return $this->newMid;
    }
    // If we reached this point, we didn't have an existing file/media,
    // the source existed, but something happened in the downloading process.
    $migrate_executable->saveMessage("File {$value['uri']} could not be created. Skipping.");
    return NULL;
  }

}
