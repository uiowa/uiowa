<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\paragraphs\Entity\Paragraph;

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
    if ($migration->id() == 'd7_file') {
      $row = $event->getRow();
      $fids = $event->getDestinationIdValues();
      $this->makeEntity($row, $fids);
    }
    elseif ($migration->id() == 'd7_page') {
      $row = $event->getRow();
      $nids = $event->getDestinationIdValues();
      $this->createParagraph($row, $nids);
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
      // Currently handles images and documents.
      // May need to check for other file types.
      switch ($fileType) {
        case 'image':
          $meta = $row->getSourceProperty('meta');
          $media = $entityManager->getStorage('media')->create([
            'bundle' => 'image',
            'field_media_image' => [
              'target_id' => $fids[0],
              'alt' => $meta['field_file_image_alt_text_value'],
              'title' => $meta['field_file_image_title_text_value'],
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

  /**
   * Edits the (blank) text paragraph associated with the new node.
   *
   * Rollback functionality is preserved this way.
   */
  public function createParagraph($row, $nids) {
    $newContent = $row->getSourceProperty('body_value');

    // Load our newly created node and get the default content block.
    $node = entity_load('node', $nids[0]);
    $section_target = end($node->get('field_page_content_block')->getValue());
    $section = Paragraph::load($section_target['target_id']);

    $paragraph_target = end($section->get('field_section_content_block')->getValue());
    $paragraph = Paragraph::load($paragraph_target['target_id']);

    $paragraph->set('field_text_body', $newContent);
    $paragraph->save();
    $node->save();
  }

}
