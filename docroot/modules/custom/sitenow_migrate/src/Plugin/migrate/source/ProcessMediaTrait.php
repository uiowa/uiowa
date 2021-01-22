<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Core\File\FileSystemInterface;
use Drupal\migrate\MigrateException;

/**
 * Provides functions for processing media in source plugins.
 */
trait ProcessMediaTrait {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Regex to find Drupal 7 JSON for inline embedded files.
   */
  public function entityReplace($match) {
    $fid = $match[1];
    $file_data = $this->fidQuery($fid);
    if ($file_data) {
      $uuid = $this->getMid($file_data['filename'])['uuid'];
      return $this->constructInlineEntity($uuid);
    }
    // Failed to find a file, so let's leave the content unchanged.
    return $match;
  }

  /**
   * Simple query to get info on the Drupal 7 file based on fid.
   */
  public function fidQuery($fid) {
    $query = $this->select('file_managed', 'f')
      ->fields('f')
      ->condition('f.fid', $fid);
    $results = $query->execute();
    return $results->fetchAssoc();
  }

  /**
   * Fetch the media uuid based on the provided filename.
   */
  public function getMid($filename) {
    $connection = \Drupal::database();
    $query = $connection->select('file_managed', 'f');
    $query->join('media__field_media_image', 'fmi', 'f.fid = fmi.field_media_image_target_id');
    $query->join('media', 'm', 'fmi.entity_id = m.mid');
    return $query->fields('m', ['uuid'])
      ->condition('f.filename', $filename)
      ->execute()
      ->fetchAssoc();
  }

  /**
   * Build the new inline embed entity format for Drupal 8 images.
   */
  public function constructInlineEntity($uuid) {
    $parts = [
      '<drupal-entity',
      'data-embed-button="media_entity_embed"',
      'data-entity-embed-display="view_mode:media.full"',
      'data-entity-embed-display-settings=""',
      'data-entity-type="media"',
      'data-entity-uuid="' . $uuid . '"',
      'data-langcode="en">',
      '</drupal-entity>',
    ];
    return implode(" ", $parts);
  }

  /**
   * Fetch the media id based on the original site's fid.
   */
  protected function getFid($original_fid, $migrate_map = 'migrate_map_d7_file') {
    $connection = \Drupal::database();
    $query = $connection->select($migrate_map, 'mm');
    $query->join('media__field_media_image', 'fmi', 'mm.destid1 = fmi.field_media_image_target_id');
    $result = $query->fields('fmi', ['entity_id'])
      ->condition('mm.sourceid1', $original_fid)
      ->execute();
    $new_fid = $result->fetchField();
    return $new_fid;
  }

  /**
   * Download a remote file to the destination file directory.
   *
   * @param string $filename
   *   Filename of the file to be downloaded.
   * @param string $source_base_path
   *   The base path for files at the source.
   * @param string $drupal_file_directory
   *   The base path for the file directory to place the downloaded file.
   *
   * @return int|bool
   *   Returns the fid of the new file record or FALSE if there is
   *   an issue.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function downloadFile($filename, $source_base_path, $drupal_file_directory) {
    print("Downloading $filename\n");
    // Suppressing errors, because we expect there to be at least some
    // private:// files or 404 errors.
    $raw_file = @file_get_contents($source_base_path . $filename);
    if (!$raw_file) {
      print("No raw file, returning.\n");
      return FALSE;
    }

    // Prepare directory in case it doesn't already exist.
    $dir = $this->fileSystem
      ->dirname($drupal_file_directory . $filename);
    if (!$this->fileSystem
      ->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      // Something went seriously wrong.
      throw new MigrateException("Could not create or write to directory '{$dir}'");
    }

    // Try to write the file.
    $file = file_save_data($raw_file, $drupal_file_directory . $filename);

    print_r($file);

    // If we have a file, continue.
    if ($file) {
      // Get a connection for the destination database.
      $connection = \Drupal::database();
      $query = $connection->select('file_managed', 'f');
      return $query->fields('f', ['fid'])
        ->condition('f.filename', $filename)
        ->execute()
        ->fetchField();
    }

    return FALSE;
  }

  /**
   * Create a media entity for images.
   *
   * @param int $fid
   *   File id for the media being created.
   * @param array $meta
   *   Associative array holding the title and alt texts.
   * @param int $owner_id
   *   User id for the media owner, or default to anonymous.
   *
   * @return false|int|string|null
   *   Media id, if successful, or else false.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createMediaEntity($fid, array $meta, $owner_id = 0) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fid);

    if ($file) {
      $fileType = explode('/', $file->getMimeType())[0];
      // Currently handles images and documents.
      // May need to check for other file types.
      switch ($fileType) {

        case 'image':
          /** @var \Drupal\Media\MediaInterface $media */
          $media = $this->entityTypeManager->getStorage('media')->create([
            'bundle' => 'image',
            'field_media_image' => [
              'target_id' => $fid,
              'alt' => $meta['alt'],
              'title' => $meta['title'],
            ],
            'langcode' => 'en',
          ]);

          // Need to truncate the title prior to setting the media name
          // due to media.name column schema restriction.
          if (strlen($meta['title']) > 255) {
            // Break at a word. Doesn't make a perfect title,
            // but preserves some of the original intention.
            $title = wordwrap($meta['title'], 255);
            $title = substr($title, 0, strpos($title, '\n'));
          }
          else {
            $title = $meta['title'];
          }
          $media->setName($title);
          $media->setOwnerId($owner_id);
          $media->save();
          print("Media item saved: {$media->uuid()}\n");
          return $media->id();

        case 'application':
        case 'document':
        case 'file':
          /** @var \Drupal\Media\MediaInterface $media */
          $media = $this->entityTypeManager->getStorage('media')->create([
            'bundle' => 'file',
            'field_media_file' => [
              'target_id' => $fid,
              'display' => 1,
              'description' => '',
            ],
            'langcode' => 'en',
            'metadata' => [],
          ]);

          $media->setName($file->getFileName());
          $media->setOwnerId($owner_id);
          $media->save();
          return $media->id();

        default:
          return FALSE;
      }
    }
  }

}
