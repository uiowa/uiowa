<?php

namespace Drupal\sitenow_migrate\Plugin\migrate;

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
   *
   * @return false|int|string|null
   *   Media id, if successful, or else false.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createMediaEntity($fid, array $meta = [], $owner_id = 1) {
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
          break;

        case 'application':
        case 'document':
        case 'file':
          $media_entity['bundle'] = 'file';
          $media_entity['field_media_file'] = [
            'target_id' => $fid,
            'display' => 1,
            'description' => '',
          ];
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
        $media->setName($meta['filename']);
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

}
