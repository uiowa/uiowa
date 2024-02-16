<?php

namespace Drupal\sitenow_migrate\Plugin\migrate;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Row;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;

/**
 * A trait for handling on-demand media creation.
 */
trait CreateMediaTrait {
  /**
   * The Media storage.
   *
   * @var \Drupal\media\MediaStorage
   */
  protected $mediaStorage;

  /**
   * Create a media entity for files.
   *
   * @param int $fid
   *   File id for the media being created.
   * @param array $meta
   *   Associative array metadata for the file such as alt and title.
   * @param int $owner_id
   *   User id for the media owner, or default to the administrator account.
   * @param string $global_caption
   *   The optional image global caption.
   *
   * @return false|int|string|null
   *   Media id, if successful, or else false.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createMediaEntity($fid, array $meta = [], $owner_id = 1, $global_caption = NULL) {
    $file_manager = $this->entityTypeManager->getStorage('file');
    /** @var \Drupal\file\FileInterface $file */
    $file = $file_manager->load($fid);

    if ($file) {
      // Check if the filename is set in $meta and set it based on the filename
      // if not.
      if (!isset($meta['filename'])) {
        $meta['filename'] = $file->getFilename();
      }

      // Populate the $media_entity with relevant fields per media type.
      $fileType = explode('/', $file->getMimeType())[0];
      $media_entity = [
        'langcode' => 'en',
        'metadata' => [],
      ];
      switch ($fileType) {
        case 'image':
          // Check if we have a title/alt,
          // and create them if not.
          foreach (['title', 'alt'] as $name) {
            if (empty($meta[$name])) {
              // If no title, set it to the filename.
              // If no alt, set it to the title
              // (which may be the filename).
              $meta[$name] = $meta['title'] ?? $meta['filename'];
            }
            // Need to truncate the title prior to setting the media name
            // due to media.name column schema restriction.
            if (strlen($meta[$name]) > 255) {
              // Break at a word. Doesn't make a perfect title,
              // but preserves some of the original intention.
              $meta[$name] = wordwrap($meta[$name], 255);
              $meta[$name] = substr($meta[$name], 0, strpos($meta[$name], '\n'));
            }
          }
          $media_entity['bundle'] = 'image';
          $media_entity['field_media_image'] = [
            'target_id' => $fid,
            'alt' => $meta['alt'],
            'title' => $meta['title'],
          ];
          if ($global_caption) {
            $media_entity['field_media_caption'] = [
              'value' => $global_caption,
              'format' => 'minimal',
            ];
          }
          break;

        case 'application':
        case 'document':
        case 'file':
          $media_entity['bundle'] = 'file';
          $media_entity['field_media_file'] = [
            'target_id' => $fid,
            'display' => 1,
          ];
          // If we have a title,
          // go ahead and set it as the description
          // so it can be used in displays.
          if (isset($meta['title'])) {
            $media_entity['field_media_file']['description'] = $meta['title'];
          }
          else {
            // If we didn't have a title, check if we had
            // a human-readable filename to use for the description
            // that doesn't match the true filename, which
            // would be used in displays with an empty description.
            if (isset($meta['file_uri']) && !str_ends_with($meta['file_uri'], $meta['filename'])) {
              $media_entity['field_media_file']['description'] = $meta['filename'];
            }
          }
          break;

        case 'audio':
          $media_entity['bundle'] = 'audio';
          $media_entity['field_media_audio_file'] = [
            'target_id' => $fid,
          ];
          break;

        default:
          return FALSE;
      }
      // Proceed if we have defined our entity bundle.
      if (isset($media_entity['bundle'])) {
        // Generate media entity and save it.
        $media_manager = $this->entityTypeManager->getStorage('media');
        /** @var \Drupal\Media\MediaInterface $media */
        $media = $media_manager->create($media_entity);
        // Check if we have a title, and if not, grab the filename
        // in order to set the media name. In some setups, this filename
        // will be a human readable name, suitable for use as a media name.
        $name = (isset($meta['title'])) ? $meta['title'] : $meta['filename'];
        $media->setName($name);
        $media->setOwnerId($owner_id);
        $media->save();
        $id = $media->id();

        // Minor memory cleanup.
        $media = NULL;
        $file = NULL;
        $media_manager = NULL;
        $file_manager = NULL;
        $media_entity = NULL;

        // We return the media ID so that it can be attached to the field.
        return $id;
      }
    }
    return FALSE;
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
      elseif ($files_dir = $this->sourcePublicFilePath) {
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
      ->accessCheck(TRUE)
      ->execute();

    if (empty($files)) {
      return FALSE;
    }

    // Get First file (make a loop if you get many files)
    $fileId = array_shift($files);

    // Array of Medias which contains your file.
    $medias = $this->entityTypeManager
      ->getStorage('media')
      ->loadByProperties(['field_media_image' => $fileId]);
    if (empty($medias)) {
      return FALSE;
    }
    // Again, let's grab the first.
    $media = array_shift($medias);
    return $media->id();
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

  /**
   * From file id, check if an oembed media exists, and create if not.
   */
  protected function createVideo($fid, $alignment = 'center') {
    $file_query = $this->fidQuery($fid);
    // Get the video source.
    $vid_uri = str_replace('oembed://', '', $file_query['uri']);
    $vid_uri = urldecode($vid_uri);
    $new_id = \Drupal::database()->select('media__field_media_oembed_video', 'o')
      ->fields('o', ['entity_id'])
      ->condition('o.field_media_oembed_video_value', $vid_uri, '=')
      ->execute()
      ->fetchField();
    if (!$new_id) {
      $media_entity = [
        'langcode' => 'en',
        'metadata' => [],
        'bundle' => 'remote_video',
        'field_media_oembed_video' => $vid_uri,
      ];

      $media_manager = $this->entityTypeManager->getStorage('media');
      /** @var \Drupal\Media\MediaInterface $media */
      $media = $media_manager->create($media_entity);
      $media->setName($file_query['filename']);
      $media->setOwnerId(1);
      $media->save();

      $uuid = $media->uuid();
    }
    else {
      // Get the uuid.
      $uuid = \Drupal::service('entity_type.manager')
        ->getStorage('media')
        ->load($new_id)
        ->uuid();
    }

    $media = [
      '#type' => 'html_tag',
      '#tag' => 'drupal-media',
      '#attributes' => [
        'data-align' => $alignment,
        'data-entity-type' => 'media',
        'data-entity-uuid' => $uuid,
        'data-view-mode' => 'medium',
      ],
    ];

    return \Drupal::service('renderer')->renderPlain($media);
  }

}
