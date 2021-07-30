<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for post-row save migrate event.
 *
 * @package Drupal\sitenow_migrate\EventSubscriber
 */
class PostRowSaveEvent implements EventSubscriberInterface {

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * PostRowSaveEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get subscribed events.
   *
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onPostRowSave'];
    return $events;
  }

  /**
   * Calls for additional processing after migration import.
   *
   * {@inheritdoc}
   */
  public function onPostRowSave($event) {
    $migration = $event->getMigration();
    switch ($migration->id()) {

      // Calls for creating a media entity for imported files.
      case 'd7_file':
        $row = $event->getRow();
        $fids = $event->getDestinationIdValues();
        $this->makeEntity($row, $fids);
        break;
    }
  }

  /**
   * Create a media entity for images.
   */
  public function makeEntity($row, $fids) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fids[0]);

    if ($file) {
      $fileType = explode('/', $file->getMimeType())[0];
      // Currently handles images and documents.
      // May need to check for other file types.
      switch ($fileType) {

        case 'image':
          $meta = $row->getSourceProperty('meta');
          $title = 'field_file_image_title_text_value';
          $alt = 'field_file_image_alt_text_value';
          foreach ([$title, $alt] as $name) {
            if (empty($meta[$name])) {
              // If no title, set it to the filename.
              // If no alt, set it to the title
              // (which may be the filename).
              $meta[$name] = (isset($meta[$title])) ? $meta[$title] : $file->getFilename();
            }
          }

          /** @var \Drupal\Media\MediaInterface $media */
          $media = $this->entityTypeManager->getStorage('media')->create([
            'bundle' => 'image',
            'field_media_image' => [
              'target_id' => $fids[0],
              'alt' => $meta[$alt],
              'title' => $meta[$title],
            ],
            'langcode' => 'en',
          ]);

          $media->setName($meta['field_file_image_title_text_value']);
          $media->setOwnerId(0);
          $media->save();
          break;

        case 'application':
        case 'document':
        case 'file':
          /** @var \Drupal\Media\MediaInterface $media */
          $media = $this->entityTypeManager->getStorage('media')->create([
            'bundle' => 'file',
            'field_media_file' => [
              'target_id' => $fids[0],
              'display' => 1,
              'description' => '',
            ],
            'langcode' => 'en',
            'metadata' => [],
          ]);

          $media->setName($file->getFileName());
          $media->setOwnerId(0);
          $media->save();
          break;

        default:
          return;
      }
    }
  }

}
