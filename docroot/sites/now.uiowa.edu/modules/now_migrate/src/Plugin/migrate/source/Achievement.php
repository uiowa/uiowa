<?php

namespace Drupal\now_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "now_achievement",
 *   source_module = "node"
 * )
 */
class Achievement extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Process the primary media field.
    $media = $row->getSourceProperty('field_primary_media');
    if (!empty($media)) {
      // Check if it's a video or image.
      $filemime = $this->select('file_managed', 'fm')
        ->fields('fm', ['filemime'])
        ->condition('fm.fid', $media[0]['fid'], '=')
        ->execute();
      // If it's an image, we can handle it like normal.
      if (str_starts_with($filemime, 'image')) {
        $fid = $this->processImageField($media[0]['fid'], $media[0]['alt'], $media[0]['title']);
        $row->setSourceProperty('field_primary_media', $fid);
      }
      elseif ($filemime === 'video/oembed') {
        $body = $row->getSourceProperty('body');
        $body[0]['value'] = $this->createVideo($media[0]['fid']) . $body[0]['value'];
        $row->setSourceProperty('body', $body);
      }
    }

    // Set our tagMapping if it's not already.
    if (empty($this->tagMapping)) {
      $this->tagMapping = \Drupal::database()
        ->select('taxonomy_term_field_data', 't')
        ->fields('t', ['name', 'tid'])
        ->condition('t.vid', 'tags', '=')
        ->execute()
        ->fetchAllKeyed();
    }

    // Map various old fields into Tags.
    $tag_tids = [];
    foreach ([
      'field_achievement_category',
      'field_news_from',
      'field_news_about',
      'field_news_for',
      'field_news_keywords',
    ] as $field_name) {
      $values = $row->getSourceProperty($field_name);
      if (!isset($values)) {
        continue;
      }
      foreach ($values as $tid_array) {
        $tag_tids[] = $tid_array['tid'];
      }
    }

    if (!empty($tag_tids)) {
      // Fetch tag names based on TIDs from our old site.
      $tag_results = $this->select('taxonomy_term_data', 't')
        ->fields('t', ['name'])
        ->condition('t.tid', $tag_tids, 'IN')
        ->execute();
      $tags = [];
      foreach ($tag_results as $result) {
        $tag_name = $result['name'];
        $tid = $this->createTag($tag_name);

        // Add the mapped TID to match our tag name.
        $tags[] = $tid;

      }
      $row->setSourceProperty('tags', $tags);
    }

    return TRUE;
  }

  /**
   * Helper function to check for existing tags and create if they don't exist.
   */
  private function createTag($tag_name) {
    // Check if we have a mapping. If we don't yet,
    // then create a new tag and add it to our map.
    if (!isset($this->tagMapping[$tag_name])) {
      $term = Term::create([
        'name' => $tag_name,
        'vid' => 'tags',
      ]);
      if ($term->save()) {
        $this->tagMapping[$tag_name] = $term->id();
      }
    }

    // Return tid for mapping to field.
    return $this->tagMapping[$tag_name];
  }

  /**
   * Helper function to check for oembed videos, or create media if not.
   */
  private function createVideo($fid) {
    $file_query = $this->fidQuery($fid);
    // Get the video source.
    $vid_uri = str_replace('oembed://', '', $file_query['uri']);

    $new_id = \Drupal::database()->select('media__field_media_oembed_video', 'o')
      ->fields('o', ['entity_id'])
      ->condition('o.field_media_oembed_video_value', $vid_uri, '=')
      ->execute()
      ->fetchField();
    if (!$new_id) {
      // @todo Do some stuff to make it.
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
        'data-align' => 'center',
        'data-entity-type' => 'media',
        'data-entity-uuid' => $uuid,
      ],
    ];

    return \Drupal::service('renderer')->renderPlain($media);
  }

}
