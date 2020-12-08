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
      case 'd7_grad_file':
        $row = $event->getRow();
        $fids = $event->getDestinationIdValues();
        $this->makeEntity($row, $fids);
        break;

      // Body content needs to be put into paragraph for Basic Pages.
      case 'd7_page-deprecated':
        $row = $event->getRow();
        $nids = $event->getDestinationIdValues();
        $this->createParagraph($row, $nids);

        break;

      // Inefficient node_load but body/format migration won't correctly attach.
      case 'd7_article':
      case 'd7_person':
      case 'd7_page':
      case 'd7_grad_article':
        $nids = $event->getDestinationIdValues();

        /** @var \Drupal\node\NodeInterface $node */
        $node = $this->entityTypeManager->getStorage('node')->load($nids[0]);

        if ($node->getType() == 'article' || $node->getType() == 'page') {
          $node->body->format = 'filtered_html';
        }
        else {
          $node->field_person_bio->format = 'filtered_html';
        }

        $node->save();
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

          /** @var \Drupal\Media\MediaInterface $media */
          $media = $this->entityTypeManager->getStorage('media')->create([
            'bundle' => 'image',
            'field_media_image' => [
              'target_id' => $fids[0],
              'alt' => $meta['field_file_image_alt_text_value'],
              'title' => $meta['field_file_image_title_text_value'],
            ],
            'langcode' => 'en',
            'thumbnail' => [
              'target_id' => $fids[0],
              'alt' => $meta['field_file_image_alt_text_value'],
            ],
          ]);

          $media->setName($meta['field_file_image_title_text_value']);
          $media->setOwnerId(0);
          $media->save();
          break;

        case 'application':
        case 'document':
        case 'file':
          // Check if there's a generic.png already.
          $generic = $this->entityTypeManager->getStorage('file')
            ->loadByProperties(['filename' => 'generic.png']);
          $gen_id = ($generic) ? array_values($generic)[0] : null;
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
            'thumbnail' => [
              'target_id' => $gen_id,
              'alt' => $file->getFileName(),
            ]
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
   * Edits the empty text paragraph associated with the new node.
   *
   * Rollback functionality is preserved this way, as well.
   */
  public function createParagraph($row, $nids) {
    $newContent = $row->getSourceProperty('body_value');

    // Load our newly created node and get the default content block.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($nids[0]);

    $section_target = end($node->get('field_page_content_block')->getValue());

    /** @var \Drupal\paragraphs\ParagraphInterface $section */
    $section = $this->entityTypeManager->getStorage('paragraph')->load($section_target['target_id']);

    $paragraph_target = end($section->get('field_section_content_block')->getValue());

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->load($paragraph_target['target_id']);

    $paragraph->set('field_text_body', [
      'value' => $newContent,
      'format' => 'filtered_html',
    ]);

    $paragraph->save();
    $node->save();
  }

}
