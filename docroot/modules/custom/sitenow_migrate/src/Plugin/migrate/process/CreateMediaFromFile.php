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
      return $this->createMediaEntity($fid);
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

    // @todo Get things below this working!
    // @todo Check if file already exists and get file ID.
    // @todo Copy file if necessary and get file ID.
    // @todo Check if media entity already exists and return media ID.
    // @todo Create media entity if necessary and return media ID.
    //
    // The parent method will take care of our download/move/copy/rename.
    // We just need the final destination to create the file object.
    //
    try {
      $final_destination = parent::transform([$source, $destination], $migrate_executable, $row, $destination_property);
      // If this was a replace, there should be an existing file entity for it
      // And if so, we return it. Otherwise, one will be created further down.
      // @todo Check if this is needed. If it is, then above needs to be refactored.
      //   Currently, if the file already existed, we handled finding/creating
      //   its media entity above.
      if ($file = $this->getExistingFileEntity($final_destination)) {
        return $id_only ? $file->id() : ['target_id' => $file->id()];
      }
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
        'uid' => $uid,
      ]);
      $file->setPermanent();
      $file->save();
      $this->newFid = $file->id();
      $this->newMid = $this->createMediaEntity($this->newFid);

      return $this->newMid;
    }
    // If we reached this point, we didn't have an existing file/media,
    // the source existed, but something happened in the downloading process.
    $migrate_executable->saveMessage("File {$value['uri']} could not be created. Skipping.");
    return NULL;
  }

  /**
   * Check if the file exists using the filename.
   *
   * @param string $filename
   *   The filename.
   *
   * @return mixed
   *   Returns the file ID or FALSE if it doesn't exist.
   */
  public function getNewFileId($filename) {
    // Get a connection for the destination database
    // and retrieve the associated fid.
    return \Drupal::database()->select('file_managed', 'f')
      ->fields('f', ['fid'])
      ->condition('f.filename', $filename)
      ->execute()
      ->fetchField();
  }

  /**
   * Return the path we'll be writing to.
   */
  protected function getDestinationFilePath($filename) {
    return 'public://' . date('Y-m') . '/' . $filename;
  }

  /**
   * Get an existing file entity for a given URI.
   *
   * @param string $destination
   *   The destination URI.
   * @param bool $set_permanent
   *   Whether or not to set the file as permanent if it is currently temporary.
   *
   * @return \Drupal\file\FileInterface
   *   The matching file entity or NULL if no entity exists.
   */
  protected function getExistingFileEntity($destination, $set_permanent = TRUE) {
    if ($files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $destination])) {
      // Grab the first file entity with a matching uri.
      // @todo Any logic for preference when there are multiple?
      $file = reset($files);

      // Set to permanent if the file in the database is set to temporary.
      // This means that the file was probably set to be removed during
      // garbage collection, which we don't want to happen anymore since we're
      // using it.
      if ($set_permanent && !$file->isTemporary()) {
        $file->setPermanent();
        $file->save();
      }

      return $file;
    }

    return NULL;
  }

  /**
   * Get the URL of the source public files path with a trailing slash.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration row record.
   *
   * @return string
   *   The URL.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected function getSourcePublicFilesUrl(Row $row): string {
    if (isset($this->sourceBaseUrl)) {
      $base_url = rtrim($this->sourceBaseUrl, '/');

      if ($files_dir = $this->getSourceFilePath($row)) {
        return "{$base_url}/{$files_dir}/";
      }
      else {
        throw new MigrateException('Cannot process media. No public files path variable set.');
      }
    }
    else {
      throw new MigrateException('Cannot process media. No source base URL set.');
    }
  }

  /**
   * Convenience method to return default scheme for files.
   */
  protected function getDefaultScheme() {
    return \Drupal::config('system.file')
      ->get('default_scheme');
  }

  /**
   * Get the source file path.
   */
  protected function getSourceFilePath(Row $row) {
    return $row->getSourceProperty('constants')['source_file_path'] ?? '';
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

  /**
   * Get an existing media entity ID based on a filename.
   */
  public function getMidByFilename($filename, $type = 'image') {
    // Array of Medias witch contains your file.
    // Load file by filename
    // array.
    $files = $this->entityTypeManager
      ->getStorage('file')
      ->getQuery()
      ->condition('filename', $filename)
      ->execute();

    if (empty($files)) {
      return FALSE;
    }

    // Get First file (make a loop if you get many files)
    $fileId = array_shift($files)->fid->value;

    // Array of Medias witch contains your file.
    $this->entityTypeManager
      ->getStorage('media')
      ->loadByProperties(['field_media_image' => $fileId]);
  }

  /**
   * Check if a source exists.
   */
  protected function sourceExists($path) {
    if ($this->isLocalUri($path)) {
      return is_file($path);
    }
    else {
      try {
        $method = !empty($this->configuration['source_check_method']) ? $this->configuration['source_check_method'] : 'HEAD';
        $options = !empty($this->configuration['guzzle_options']) ? $this->configuration['guzzle_options'] : [];
        \Drupal::httpClient()->request($method, $path, $options);
        return TRUE;
      }
      catch (ServerException $e) {
        return FALSE;
      }
      catch (ClientException $e) {
        return FALSE;
      }
      catch (ConnectException $e) {
        return FALSE;
      }
    }
  }

}
