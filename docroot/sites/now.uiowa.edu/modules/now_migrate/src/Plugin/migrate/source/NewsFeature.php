<?php

namespace Drupal\now_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\UrlHelper;
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
   * @todo Remove this when redirect testing is done.
   */
  public function query() {
    $query = parent::query();
    $query->condition('n.nid', $this->withRedirects(), 'IN');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

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

    // Before doing anything with the body, check if empty.
    // If empty, check for external redirects.
    if (empty($body) || $body[0]['value'] == '') {
      $nid = $row->getSourceProperty('nid');
      $node_path = 'node/' . $nid;

      // Establish an array of paths to check for redirects.
      $paths = [$node_path];

      $aliases = $this->select('url_alias', 'a')
        ->fields('a', ['alias'])
        ->condition('a.source', $node_path, 'IN')
        ->execute();

      // Add any results to the paths to check against array.
      if ($aliases) {
        foreach ($aliases as $result) {
          $paths[] = $result['alias'];
        }
      }

      // Check and return any redirects for the paths in our array.
      $redirects = $this->select('redirect', 'r')
        ->fields('r', ['redirect'])
        ->condition('r.source', $paths, 'IN')
        ->execute();

      if ($redirects) {
        // There should just be one, but use the external/last one.
        foreach ($redirects as $redirect) {
          if (UrlHelper::isExternal($redirect['redirect'])) {
            $target = $redirect['redirect'];
          }
        }
        if ($target) {
          $row->setSourceProperty('field_article_source_link_direct', 1);
          $row->setSourceProperty('custom_source_link', $target);
          $this->logger->notice($this->t('From original node @nid, added source link based on @redirect redirect.', [
            '@redirect' => $target,
            '@nid' => $nid,
          ]));
        }
      }
    }

    if (!empty($body)) {
      // Check for a subhead, and prepend it to the body if so.
      $subhead = $row->getSourceProperty('field_subhead');
      if (!empty($subhead)) {
        $subhead = '<p class="uids-component--light-intro">' . $subhead[0]['value'] . '</p>';
        $body[0]['value'] = $subhead . $body[0]['value'];
      }
      $this->viewMode = 'medium__no_crop';
      $this->align = 'left';

      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      // Check for captions in the old format, and if found,
      // manually insert them into the drupal-media element.
      $body[0]['value'] = preg_replace_callback('%<div class=\"(image|video)-(.*?)-(.*?)\">(<drupal-media.*?)><\/drupal-media>(.*?)<\/div>%is', [
        $this,
        'captionReplace',
      ], $body[0]['value']);
      // Check for callouts in the source,
      // and construct the proper format for the destination.
      $body[0]['value'] = preg_replace_callback('|<div class=\"(.*?)-callout\">(.*?)<\/div>|is', [
        $this,
        'calloutReplace',
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
      $gallery_query = $this->select('field_data_field_gallery_photos', 'g')
        ->fields('g')
        ->condition('g.entity_id', $gallery[0]['target_id'], '=');
      // Grab title and alt directly from these tables,
      // as they are the most accurate for the photo gallery images.
      $gallery_query->join('field_data_field_file_image_title_text', 'title', 'g.field_gallery_photos_fid = title.entity_id');
      $gallery_query->join('field_data_field_file_image_alt_text', 'alt', 'g.field_gallery_photos_fid = alt.entity_id');
      $images = $gallery_query->fields('title')
        ->fields('alt')
        ->execute();
      $new_images = [];
      foreach ($images as $image) {
        // On the source site, the image title is used as the caption
        // in photo galleries, so pass it in as the global caption
        // parameter for the new site.
        $new_images[] = $this->processImageField($image['field_gallery_photos_fid'], $image['field_file_image_alt_text_value'], $image['field_file_image_title_text_value'], $image['field_file_image_title_text_value']);
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

    // If there's a byline, prepend 'By: ' to it
    // since it is being added to the article source field,
    // and would otherwise be unlabeled.
    $byline = $row->getSourceProperty('field_by_line');
    if (!empty($byline)) {
      $byline = 'Written by: ' . $byline[0]['value'];
      $row->setSourceProperty('field_by_line', $byline);
    }

    return TRUE;
  }

  /**
   * Helper function to add an image caption during a preg_replace.
   */
  private function captionReplace($match) {

    // Match[1] denotes whether it is an image or video.
    // Match[2] is the alignment in the source.
    // Match[3] is the pixel-width in the source.
    // Match[4] is most of the drupal-media element,
    // and match[5] is the image caption.
    // Here we're adding the caption and then re-closing
    // the drupal-media element.
    // First, remove extra breaks that were used in source for
    // visual spacing.
    $match[5] = preg_replace('%(<br>|<br \/>)%is', ' ', $match[5]);
    // Then remove any extraneous spaces.
    $match[5] = trim(preg_replace('/\s\s+/', ' ', $match[5]));

    // Process the alignment and size in here as well.
    // Because they are part of a wrapper div, it has to be processed
    // here, as our base tooling can't currently handle wrappers.
    switch ($match[1]) {
      case 'video':
        $size = match ($match[3]) {
          '320' => 'small',
          // 640 or default should go to medium.
          default => 'medium',
        };
        break;

      case 'image':
      default:
        $size = match ($match[3]) {
          '150' => 'small__no_crop',
          '640' => 'large__no_crop',
          // 320 or default should go to medium.
          default => 'medium__no_crop',
        };
    }
    $match[4] = preg_replace('%(data-align=\")(.*?)(\")%is', '$1' . $match[2] . '$3', $match[4]);
    $match[4] = preg_replace('%(data-view-mode=\")(.*?)(\")%is', '$1' . $size . '$3', $match[4]);

    return $match[4] . ' data-caption="' . $match[5] . '"></drupal-media>';
  }

  /**
   * Helper function to update a callout during a preg_replace.
   */
  private function calloutReplace($match) {
    // Match[1] is the "left" or "right" of the callout
    // alignment. Match[2] is the interior content of the <div>.
    // Extra spacings have been added in various places
    // for visual spacing. Remove them, or they'll throw
    // things off in the new callout component.
    $match[2] = preg_replace('|(<br>)+|is', '<br>', $match[2]);

    // Look for a headline to use in the callout, as well as any additional
    // line breaks. Like before, here they are unnecessary.
    $headline = '';
    if (preg_match('|<strong>(.*?)<\/strong>(<br>)*|is', $match[2], $headline_matches)) {
      // @todo Update this with proper callout headline construction.
      $headline = '<h3>' . $headline_matches[1] . '</h3>';
      // If we're adding the headline separately,
      // remove it from the rest of the text, so we don't duplicate.
      $match[2] = str_replace($headline_matches[0], '', $match[2]);
    }

    // @todo Update this with proper callout construction.
    return '<div class="callout">' . $headline . $match[2] . '</div>';
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
   * @todo Remove this when redirect testing is done.
   */
  private function withRedirects(): array {
    return [23636, 24251, 24456, 25131, 25136, 25271, 25891, 26591, 26696, 26861, 27531, 27786, 27981, 28096, 28786, 28791, 29146, 29231, 29841, 30161, 30296, 30346, 30511, 30716, 31096, 31101, 31106, 31111, 31116, 31386, 31451, 31556, 31691, 31886, 32251, 32376, 32406, 32411, 32481, 32636, 32851, 32931, 32991, 33211, 33246, 33351, 33376, 33451, 33606, 33696, 33716, 33861, 33896, 34116];
  }

}
