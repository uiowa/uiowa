<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
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
   * The default image view_mode.
   *
   * @var string
   */
  protected $viewMode = 'medium__no_crop';

  /**
   * Get the URL of the source public files path with a trailing slash.
   *
   * @return string
   *   The URL.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected function getSourcePublicFilesUrl(): string {
    if (isset($this->configuration['constants']) && isset($this->configuration['constants']['source_base_path'])) {
      $base_url = rtrim($this->configuration['constants']['source_base_path'], '/');

      if ($files_dir = $this->variableGet('file_public_path', NULL)) {
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
   * Return the path we'll be writing to.
   */
  protected function getDrupalFileDirectory() {
    return 'public://' . date('Y-m') . '/';
  }

  /**
   * Regex replace for inline files or images.
   *
   * @param string $content
   *   Body content that should be checked and updated.
   *
   * @return string
   *   The updated body content with inline replacements.
   */
  public function replaceInlineFiles($content) {
    return preg_replace_callback("|\[\[\{.*?\"fid\":\"(.*?)\".*?\]\]|", [
      $this,
      'entityReplace',
    ], $content);
  }

  /**
   * Regex to find Drupal 7 JSON for inline embedded files.
   */
  public function entityReplace($match) {
    // FID is the matched subgroup.
    $fid = $match[1];
    // Decode to JSON associative array.
    // The actual JSON data is surrounded by two sets of brackets,
    // so the matched, non-bracketed JSON is in the [0][0] index
    // of the json_decode result.
    $file_properties = json_decode($match[0], TRUE)[0][0];
    $align = isset($file_properties['fields']['alignment']) ? $file_properties['fields']['alignment'] : '';
    $file_data = $this->fidQuery($fid);

    if ($file_data) {
      $filename = $file_data['filename'];
      $uuid = $this->getMid($filename)['uuid'];
      if (!$uuid) {
        $new_fid = \Drupal::database()->select('file_managed', 'f')
          ->fields('f', ['fid'])
          ->condition('f.filename', $filename)
          ->execute()
          ->fetchField();

        $meta = [
          'title' => isset($file_properties['attributes']['title']) ? $file_properties['attributes']['title'] : $filename,
          'alt' => isset($file_properties['attributes']['alt']) ? $file_properties['attributes']['alt'] : explode('.', $filename)[0],
        ];

        // If there's no fid in the D8 database,
        // then we'll need to fetch it from the source.
        if (!$new_fid) {
          $uri = $file_data['uri'];
          $filename_w_subdir = str_replace('public://', '', $uri);

          // Split apart the filename from the subdirectory path.
          $filename_w_subdir = explode('/', $filename_w_subdir);
          $filename = array_pop($filename_w_subdir);
          $subdir = implode('/', $filename_w_subdir) . '/';
          unset($filename_w_subdir);
          $new_fid = $this->downloadFile($filename, $this->getSourcePublicFilesUrl() . $subdir, $this->getDrupalFileDirectory() . $subdir);
          if ($new_fid) {
            $this->createMediaEntity($new_fid, $meta, 1);
            $uuid = $this->getMid($filename)['uuid'];
          }
        }
        else {
          $uuid = $this->getMid($filename)['uuid'];

          // And in case we had the file, but not the media entity.
          if (!$uuid) {
            $this->createMediaEntity($new_fid, $meta, 1);
            $uuid = $this->getMid($filename)['uuid'];
          }
        }
      }

      unset($file_data);
      unset($file_properties);
      return isset($uuid) ? $this->constructInlineEntity($uuid, $align) : '';
    }

    // Failed to find a file, so let's leave the content unchanged.
    return $match;
  }

  /**
   * Build the new inline embed entity format for Drupal 8 images.
   *
   * @param string $uuid
   *   The unique identifier for the media to embed.
   * @param string $align
   *   The image alignment. Center if empty.
   * @param string $view_mode
   *   The image format. Defaults to 'small__no_crop'.
   *
   * @return string
   *   Returns markup as a plaintext string.
   */
  public function constructInlineEntity(string $uuid, string $align, $view_mode = '') {
    $align = isset($align) ? $align : 'center';

    $media = [
      '#type' => 'html_tag',
      '#tag' => 'drupal-media',
      '#attributes' => [
        'data-align' => $align,
        'data-entity-type' => 'media',
        'data-entity-uuid' => $uuid,
        'data-view-mode' => !empty($view_mode) ? $view_mode : $this->viewMode,
      ],
    ];

    return \Drupal::service('renderer')->renderPlain($media);
  }

  /**
   * Simple query to get info on the Drupal 7 file based on fid.
   *
   * @param int $fid
   *   The file id to query against.
   *
   * @return array
   *   Return associative array of file information for the given fid.
   */
  public function fidQuery($fid) {
    return $this->select('file_managed', 'f')
      ->fields('f')
      ->condition('f.fid', $fid)
      ->execute()
      ->fetchAssoc();
  }

  /**
   * Fetch the media uuid based on the provided filename.
   */
  public function getMid($filename) {
    $query = \Drupal::database()->select('file_managed', 'f');
    $query->join('media__field_media_image', 'fmi', 'f.fid = fmi.field_media_image_target_id');
    $query->join('media', 'm', 'fmi.entity_id = m.mid');
    $results = $query->fields('m', ['uuid', 'mid'])
      ->condition('f.filename', $filename)
      ->execute()
      ->fetchAssoc();

    unset($query);
    return $results;
  }

  /**
   * Fetch the media id based on the original site's fid.
   */
  protected function getFid($original_fid, $migrate_map = 'migrate_map_d7_file') {
    $query = \Drupal::database()->select($migrate_map, 'mm');
    $query->join('media__field_media_image', 'fmi', 'mm.destid1 = fmi.field_media_image_target_id');
    $results = $query->fields('fmi', ['entity_id'])
      ->condition('mm.sourceid1', $original_fid)
      ->execute()
      ->fetchField();
    unset($query);
    return $results;
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
    // Suppressing errors, because we expect there to be at least some
    // private:// files or 404 errors.
    $raw_file = @file_get_contents($source_base_path . $filename);
    if (!$raw_file) {
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

    // Try to write the file, replacing any existing file with the same name.
    $file = file_save_data($raw_file, implode('/', [$dir, $filename]), FileSystemInterface::EXISTS_REPLACE);

    // Drop the raw file out of memory for a little cleanup.
    unset($raw_file);

    // If we have a file, continue.
    if ($file) {
      // Drop the file out of memory for a little cleanup.
      Cache::invalidateTags($file->getCacheTagsToInvalidate());
      unset($file);
      // Get a connection for the destination database
      // and retrieve the id for the newly created file.
      return \Drupal::database()->select('file_managed', 'f')
        ->fields('f', ['fid'])
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
  public function createMediaEntity($fid, array $meta, $owner_id = 1) {
    $file_manager = $this->entityTypeManager->getStorage('file');
    /** @var \Drupal\file\FileInterface $file */
    $file = $file_manager->load($fid);

    if ($file) {
      $fileType = explode('/', $file->getMimeType())[0];
      // Currently handles images and documents.
      // May need to check for other file types.
      switch ($fileType) {

        case 'image':
          $media_manager = $this->entityTypeManager->getStorage('media');
          /** @var \Drupal\Media\MediaInterface $media */
          $media = $media_manager->create([
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
          $id = $media->id();
          // Minor memory cleanup.
          unset($media);
          unset($file);
          $media_manager->resetCache([$id]);
          unset($media_manager);
          $file_manager->resetCache([$fid]);
          unset($file_manager);
          return $id;

        case 'application':
        case 'document':
        case 'file':
          $media_manager = $this->entityTypeManager->getStorage('media');
          /** @var \Drupal\Media\MediaInterface $media */
          $media = $media_manager->create([
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
          $id = $media->id();
          // Minor memory cleanup.
          unset($media);
          unset($file);
          $media_manager->resetCache([$id]);
          unset($media_manager);
          $file_manager->resetCache([$fid]);
          unset($file_manager);
          return $id;

        default:
          return FALSE;
      }
    }
    return FALSE;
  }

  /**
   * Process an image field.
   *
   * @param int $fid
   *   The file ID.
   * @param string $alt
   *   The image alt text.
   * @param string $title
   *   The optional image title.
   *
   * @return int|null
   *   The media ID or null if unable to process.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function processImageField($fid, $alt = NULL, $title = NULL) {
    $uri = $this->fidQuery($fid)['uri'];
    $filename_w_subdir = str_replace('public://', '', $uri);

    // Split apart the filename from the subdirectory path.
    $filename_w_subdir = explode('/', $filename_w_subdir);
    $filename = array_pop($filename_w_subdir);
    $subdir = implode('/', $filename_w_subdir) . '/';
    unset($filename_w_subdir);

    // Get a connection for the destination database
    // and retrieve the associated fid.
    $new_fid = \Drupal::database()->select('file_managed', 'f')
      ->fields('f', ['fid'])
      ->condition('f.filename', $filename)
      ->execute()
      ->fetchField();

    $meta = [
      'alt' => $alt ?? $filename,
      'title' => $title,
    ];

    // If there's no fid in the D8 database,
    // then we'll need to fetch it from the source.
    if (!$new_fid) {
      // Use the filename, update the source base path with the subdirectory.
      $new_fid = $this->downloadFile($filename, $this->getSourcePublicFilesUrl() . $subdir, $this->getDrupalFileDirectory() . $subdir);
      unset($subdir);

      if ($new_fid) {
        $mid = $this->createMediaEntity($new_fid, $meta, 1);
      }
    }
    else {
      $mid = $this->getMid($filename)['mid'];
      unset($filename);

      // And in case we had the file, but not the media entity.
      if (!$mid) {
        $mid = $this->createMediaEntity($new_fid, $meta, 1);
        unset($meta);
      }
    }

    return $mid ?? NULL;
  }

  /**
   * Replace inline image tags with media references.
   *
   * Used this as reference: https://stackoverflow.com/a/3195048.
   *
   * @param string $content
   *   Drupal field content which may contain embedded images.
   * @param string $stub
   *   File directory stub, e.g. '/sites/vwu/files/'.
   * @param string $view_mode
   *   The default view mode to set image formatting for all inline images.
   *
   * @return string
   *   The updated field content with replaced inline images.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function replaceInlineImages(string $content, string $stub, $view_mode = '') {
    $view_mode = isset($view_mode) ? $view_mode : $this->view_mode;
    $drupal_file_directory = $this->getDrupalFileDirectory();

    // Create a HTML content fragment.
    $document = Html::load($content);

    // Get all the image from the $content.
    $images = $document->getElementsByTagName('img');

    // As we replace the inline images, they are actually
    // removed in the DOMNodeList $images, so we have to
    // use a regressive loop to count through them.
    // See https://www.php.net/manual/en/domnode.replacechild.php#50500.
    $i = $images->length - 1;

    while ($i >= 0) {
      // The current inline image element.
      $img = $images->item($i);
      $src = $img->getAttribute('src');
      // No point in continuing after this point because the
      // image is broken if we don't have a 'src'.
      if ($src) {
        // Process the 'src' into a consistent format.
        // Get the filepath and filename separated,
        // and fix any spaces in the URL prior to trying to download.
        $file_path = str_replace(' ', '%20', rawurldecode($src));
        $filename = basename($file_path);

        // If it's an external image, don't touch it
        // and continue on to the next iteration.
        if (!str_contains($file_path, $stub)) {
          $i--;
          continue;
        }
        // Attempt to get existing image.
        $fid = $this->getD8FileByFilename($filename);

        if (!$fid) {
          // Get the prefix to the path for downloading purposes.
          // Also remove URL front, in case absolute URLs to same site
          // were used.
          $prefix_path = explode($stub, $file_path);
          $prefix_path = array_pop($prefix_path);
          // And take out the filename.
          $prefix_path = str_replace($filename, '', $prefix_path);

          // Download the file and create the file record.
          $fid = $this->downloadFile($filename, $this->getSourcePublicFilesUrl() . $prefix_path, $drupal_file_directory . $prefix_path);

          // Get meta data an create the media entity.
          $meta = [];
          foreach (['alt', 'title'] as $name) {
            if ($prop = $img->getAttribute($name)) {
              $meta[$name] = $prop;
            }
          }
          // If we successfully downloaded the file, create the media entity.
          if ($fid) {
            $this->createMediaEntity($fid, $meta);
          }
        }

        // Get the media UUID.
        $uuid = $this->getMid($filename)['uuid'];

        // There is an issue at this point if we don't have an MID,
        // and we definitely don't want to replace the existing item
        // with a broken media embed.
        if ($uuid) {
          // Create the <drupal-media> element.
          $media_embed = $document->createElement('drupal-media');
          $media_embed->setAttribute('data-entity-uuid', $uuid);
          $media_embed->setAttribute('data-view-mode', $view_mode);
          $media_embed->setAttribute('data-entity-type', 'media');

          // Set the alignment if we can determine it.
          $align = $this->getImageAlign($img);
          if ($align) {
            $media_embed->setAttribute('data-align', $align);
          }

          // Replace the <img> element with the <drupal-media> element.
          $img->parentNode->replaceChild($media_embed, $img);
        }
        // If we weren't able to find or download an image,
        // let's insert a token for cleanup later.
        else {
          $token = $document->createComment('Missing image: ' . $file_path);
          // Replace the <img> element with our token comment.
          $img->parentNode->replaceChild($token, $img);
        }
      }

      unset($token);
      unset($img);
      unset($file_path);
      unset($filename);
      unset($src);
      unset($prefix_path);
      unset($meta);

      $i--;
    }

    // Convert back into a string and return it.
    $html = Html::serialize($document);
    // Do a little bit of cleanup.
    unset($images);
    unset($document);

    return $html;
  }

  /**
   * Attempt to determine the image alignment.
   */
  protected function getImageAlign($img) {
    $align = NULL;
    if ($img->getAttribute('align')) {
      $align = $img->getAttribute('align');
    }
    elseif ($img->getAttribute('style')) {
      preg_match('/(?:float: )(left|right)/i', $img->getAttribute('style'), $align_match);
      if ($align_match && !empty($align_match)) {
        $align = $align_match[1];
      }
    }

    return $align;
  }

  /**
   * Get the D7 file record using the filename.
   */
  protected function getD8FileByFilename($filename) {
    return \Drupal::database()->select('file_managed', 'f')
      ->fields('f', ['fid'])
      ->condition('f.filename', $filename)
      ->execute()
      ->fetchField();
  }

}
