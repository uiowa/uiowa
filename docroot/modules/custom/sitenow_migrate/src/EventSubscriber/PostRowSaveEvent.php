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
    $row = $event->getRow();
    $fids = $event->getDestinationIdValues();
    $this->makeEntity($row, $fids);
  }

  /**
   * Create a media entity for images.
   */
  public function makeEntity($row, $fids) {
    $entityManager = \Drupal::entityTypeManager();
    $file = $entityManager->getStorage('file')->load($fids[0]);
    if ($file) {
      // Currently only images are handled.
      // Will need to be updated for other types of files.
      $bundle = 'image';
      $alt = '';
      $title = '';
      $media = $entityManager->getStorage('media')->create([
        'bundle' => $bundle,
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
    }
  }

}
