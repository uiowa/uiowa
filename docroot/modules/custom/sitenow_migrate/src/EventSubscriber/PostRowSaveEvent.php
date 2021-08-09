<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\layout_builder\Section;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_builder\InlineBlockUsage;

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

      // @todo Remove this after ITU Physics migrate.
      case 'itu_physics_labs':
        $row = $event->getRow();
        $nids = $event->getDestinationIdValues();
        $this->addBlock($row, $nids[0]);
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

  /**
   * Add description block to the newly created node.
   */
  public function addBlock($row, $nid) {
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($nid);
    $layout = $node->get('layout_builder__layout');

    $layout_settings = [
      'label' => '',
      'column_widths' => [],
      'layout_builder_styles_style' => [],
    ];
    $section_array = [
      'layout_id' => 'layout_onecol',
      'components' => [],
      'layout_settings' => $layout_settings,
    ];

    $text = $row->getSourceProperty('description');
    // If the text block begins with a headline, grab it and
    // create a title from it.
    if (isset($text['value']) && preg_match('|\A<(h\d)>(.*?)<\/h\d>|', $text['value'], $matches)) {
      $h_level = $matches[1];
      $title = $matches[2];
      $text['value'] = str_replace(
        $matches[0],
        '',
        $text['value']
      );
    }
    $headline = [
      'headline' => isset($title) ? $title : '',
      'heading_size' => isset($h_level) ? $h_level : 'h2',
      'hide_headline' => 0,
      'headline_style' => 'default',
    ];
    $block_definition = [
      'type' => 'uiowa_text_area',
      'langcode' => 'en',
      'status' => 1,
      'reusable' => 0,
      'default_langcode' => 1,
      // getValue sets both the text value and the format.
      'field_uiowa_text_area' => $text,
      'field_uiowa_headline' => $headline,
    ];

    if (!empty($block_definition)) {
      $block = \Drupal::entityTypeManager()
        ->getStorage('block_content')
        ->create($block_definition);
    }
    if (isset($block) && $block->save()) {
      $uuid = $block->get('uuid')->getValue()[0]['value'];
      $config = [
        'id' => 'inline_block:' . $block->bundle(),
        'label' => 'Text area',
        'provider' => 'layout_builder',
        'label_display' => 0,
        'block_revision_id' => $block->getRevisionId(),
        'view_mode' => '',
      ];

      // Set the block usage to the node.
      $database = \Drupal::database();
      $use_controller = new InlineBlockUsage($database);
      $use_controller->addUsage($block->id(), $node);
    }

    if (!empty($config)) {
      $section_array['components'][$uuid] = [
        'uuid' => $uuid,
        'region' => 'content',
        'configuration' => $config,
        'additional' => [
          'layout_builder_styles_style' => []
        ],
        'weight' => 0
      ];
      $section = Section::fromArray($section_array);
      $layout->appendSection($section);
      $node->set('layout_builder__layout', $layout->getSections());
      $node->save();
    }
  }

}
