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
 *   id = "now_news_feature",
 *   source_module = "node"
 * )
 */
class NewsFeature extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * @todo Remove this when video testing is done.
   */
  public function query() {
    $query = parent::query();
    $query->condition('n.nid', $this->withVideos(), 'IN');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    $subhead = $row->getSourceProperty('field_subhead');
    if (!empty($subhead)) {
      $subhead = '<p class="uids-component--light-intro">' . $subhead[0]['value'] . '</p>';
      $body = $row->getSourceProperty('body');
      $body[0]['value'] = $subhead . $body[0]['value'];
      $row->setSourceProperty('body', $body);
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

    // Replace inline files and images in the body,
    // and set for placement in the body and teaser fields.
    $body = $row->getSourceProperty('body');
    if (!empty($body)) {
      $this->viewMode = 'medium__no_crop';
      $this->align = 'left';

      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      // Check for captions in the old format, and if found,
      // manually insert them into the drupal-media element.
      $body[0]['value'] = preg_replace_callback('|<div class=\"image-.*?\">(<drupal-media.*?)><\/drupal-media>(.*?)<\/div>|is', [
        $this,
        'captionReplace',
      ], $body[0]['value']);

      $row->setSourceProperty('body', $body);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    // Process the gallery images.
    $gallery = $row->getSourceProperty('field_photo_gallery');
    if (!empty($gallery)) {

      // The d7 galleries are a separate entity, so we need to fetch it
      // and then process the individual images attached.
      $images = $this->select('field_data_field_gallery_photos', 'g')
        ->fields('g')
        ->condition('g.entity_id', $gallery[0]['target_id'], '=')
        ->execute();
      $new_images = [];
      foreach ($images as $image) {
        $new_images[] = $this->processImageField($image['field_gallery_photos_fid'], $image['field_gallery_photos_alt'], $image['field_gallery_photos_title']);
      }
      $row->setSourceProperty('gallery', $new_images);
    }

    // Truncate the featured image caption, if needed,
    // and add a message to the migrate table for future followup.
    $caption = $row->getSourceProperty('field_primary_media_caption');
    if (!empty($caption)) {
      if (strlen($caption[0]['value']) > 255) {
        $message = 'Field image caption truncated. Original caption was: ' . $caption[0]['value'];

        // Need to limit to lower than the actual 255 limit,
        // to account for added ellipsis as well as giving
        // some buffer room for possible encoded characters like ampersands.
        $caption[0]['value'] = $this->extractSummaryFromText($caption[0]['value'], 245);
        $row->setSourceProperty('field_primary_media_caption', $caption);

        // Add a message to the migration that can be queried later.
        // The following query can then be used:
        // "SELECT migrate_map_now_news_feature.destid1 AS NODE_ID,
        // migrate_message_now_news_feature.message AS MESSAGE
        // FROM migrate_map_now_news_feature JOIN
        // migrate_message_now_news_feature ON
        // migrate_map_now_news_feature.source_ids_hash =
        // migrate_message_now_news_feature.source_ids_hash;".
        $this->migration
          ->getIdMap()
          ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
      }
    }

    // Process the primary media field.
    $media = $row->getSourceProperty('field_primary_media');
    if (!empty($media)) {

      // Check if it's a video or image.
      $filemime = $this->select('file_managed', 'fm')
        ->fields('fm', ['filemime'])
        ->condition('fm.fid', $media[0]['fid'], '=')
        ->execute()
        ->fetchField();

      // If it's an image, we can handle it like normal.
      if (str_starts_with($filemime, 'image')) {
        $fid = $this->processImageField($media[0]['fid'], $media[0]['alt'], $media[0]['title']);
        $row->setSourceProperty('field_primary_media', $fid);
        // Check the aspect ratio of the featured image.
        // If it's 3:2 or wider, set the display to use
        // the site-wide-default. If it's more square or taller,
        // or if we can't determine it,
        // set it to not display.
        if (!empty($media[0]['width'])
          && !empty($media[0]['height']
          && $media[0]['width'] / $media[0]['height'] >= 1.5)) {
          $row->setSourceProperty('featured_image_display', '');
        }
        else {
          $row->setSourceProperty('featured_image_display', 'do_not_display');
        }
      }
      elseif (in_array($filemime, ['video/oembed', 'application/octet-stream'])) {
        $body = $row->getSourceProperty('body');

        // Check to see if body has media in it, then set alignment.
        // If there is no media or the first media is far enough away,
        // left align the video, otherwise center align so that it
        // doesn't overlap later media.
        if (preg_match('/drupal-media/is', substr($body[0]['value'], 0, 700)) === 0) {
          $video = $this->createVideo($media[0]['fid'], 'right');
        }
        else {
          $video = $this->createVideo($media[0]['fid']);
        }

        $body[0]['value'] = $video . $body[0]['value'];
        $row->setSourceProperty('body', $body);
      }
    }

    return TRUE;
  }

  /**
   * Helper function to add an image caption during a preg_replace.
   */
  private function captionReplace($match) {

    // Match[1] is most of the drupal-media element,
    // and match[2] is the image caption.
    // Here we're adding the caption and then re-closing
    // the drupal-media element.
    return $match[1] . ' data-caption="' . trim($match[2]) . '"></drupal-media>';
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
   * @todo Remove this when video testing is done.
   */
  private function withVideos() {
    return [
      14772, 14811, 14866, 14957, 14969, 14999,
      15068, 15322, 15567, 15579, 15587, 15589,
      15597, 16097, 16181, 16231, 17108, 17836,
      18946, 19126, 20661, 21601, 22891,
    ];
  }

}
