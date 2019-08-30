<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class PostRowSaveEvent.
 *
 * @package Drupal\sitenow_migrate\EventSubscriber
 */
class PostRowSaveEvent implements EventSubscriberInterface {

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
   * Calls for the creation of a media entity after file creation.
   *
   * {@inheritdoc}
   */
  public function onPostRowSave($event) {
    $migration = $event->getMigration();
    // Only need this processing for the file migrations.
    if ($migration->label() == 'd7_file') {
      $row = $event->getRow();
      $fids = $event->getDestinationIdValues();
      $this->makeEntity($row, $fids);
    }
  }

  /**
   * Create a media entity for images.
   */
  public function makeEntity($row, $fids) {
    $entityManager = \Drupal::entityTypeManager();
    $file = $entityManager->getStorage('file')->load($fids[0]);
    if ($file) {
      $fileType = explode('/', $file->getMimeType())[0];
      // We currently don't retrieve the alt or title from the images.
      $alt = '';
      $title = '';

      // Currently handles images and documents.
      // May need to check for other file types.
      switch ($fileType) {
        case 'image':
          $media = $entityManager->getStorage('media')->create([
            'bundle' => 'image',
            'field_media_image' => [
              'target_id' => $fids[0],
              'alt' => $alt,
              'title' => $title,
            ],
            'langcode' => 'en',
          ]);
          $media->setName($file->getFileName());
          $media->setOwnerId(0);
          $media->save();
          break;

        case 'application':
        case 'document':
        case 'file':
          $media = $entityManager->getStorage('media')->create([
            'bundle' => 'file',
            'field_media_file' => [
              'target_id' => $fids[0],
            ],
            'langcode' => 'en',
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
