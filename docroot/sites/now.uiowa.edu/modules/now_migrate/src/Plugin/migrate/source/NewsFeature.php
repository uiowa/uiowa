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
   * @todo Remove this when photo gallery testing is done.
   */
  public function query() {
    $query = parent::query();
    $query->condition('n.nid', $this->withCallout(), 'IN');
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
      $body[0]['value'] = preg_replace_callback('|<div class=\"image-.*?\">(<drupal-media.*?)><\/drupal-media>(.*?)<\/div>|is', [
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

    // Match[1] is most of the drupal-media element,
    // and match[2] is the image caption.
    // Here we're adding the caption and then re-closing
    // the drupal-media element.
    return $match[1] . ' data-caption="' . trim($match[2]) . '"></drupal-media>';
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
   * @todo Remove this when photo gallery testing is done.
   */
  private function withGallery() {
    return [
      14581, 14582, 14583, 14584, 14585, 14586, 14587, 14588,
      14589, 14590, 14591, 14592, 14593, 14595, 14596, 14597,
      14598, 14599, 14600, 14601, 14602, 14603, 14604, 14605,
      14606, 14607, 14608, 14609, 14610, 14611, 14612, 14613,
      14614, 14615, 14616, 14617, 14618, 14619, 14620, 14621,
      14622, 14623, 14624, 14625, 14626, 14627, 14628, 14629,
      14630, 14631, 14632, 14633, 14634, 14635, 14636, 14637,
      14638, 14639, 14640, 14641, 14642, 14643, 14644, 14645,
      14646, 14647, 14648, 14649, 14650, 14651, 14652, 14653,
      14654, 14655, 14656, 14657, 14658, 14659, 14660, 14661,
      14662, 14663, 14664, 14665, 14666, 14667, 14668, 14669,
      14670, 14671, 14672, 14673, 14674, 14675, 14676, 14677,
      14678, 14679, 14680, 14681, 14682, 14683, 14684, 14685,
      14686, 14687, 14688, 14689, 14691, 14692, 14693, 14694,
      14695, 14696, 14697, 14699, 14700, 14701, 14702, 14703,
      14704, 14705, 14706, 14707, 14708, 14709, 14710, 14711,
      14712, 14713, 14714, 14715, 14716, 14717, 14718, 14719,
      14720, 14721, 14722, 14723, 14724, 14725, 14726, 14727,
      14728, 14729, 14730, 14731, 14732, 14733, 14734, 14735,
      14736, 14737, 14997, 15011, 15078, 15088, 15089, 15092,
      15118, 15133, 15143, 15174, 15211, 15272, 15325, 15374,
      15393, 15410, 15422, 15479, 15495, 15532, 15544, 15589,
      15631, 15674, 15693, 15752, 15755, 15767, 15778, 15815,
      15827, 15840, 15854, 15871, 15926, 15939, 15940, 15941,
      15960, 15971, 15990, 15991, 16016, 16027, 16032, 16046,
      16052, 16118, 16164, 16172, 16200, 16222, 16274, 16275,
      16293, 16308, 16310, 16343, 16356, 16363, 16439, 16493,
      16507, 16515, 16534, 16650, 16658, 16662, 16785, 16835,
      16850, 16852, 16853, 16873, 16899, 16949, 16950, 17013,
      17014, 17062, 17094, 17097, 17213, 17227, 17346, 17441,
      17476, 17666, 17726, 17846, 17946, 18006, 18141, 18281,
      18296, 18401, 18506, 18581, 18676, 18681, 18726, 18736,
      19046, 19171, 19271, 19656, 19991, 20136, 20341, 20711,
      20741, 20916, 21026, 21116, 21336, 21691, 21711, 21841,
      21861, 21886, 22311, 22336, 22581, 22631, 22786, 23076,
      23356, 23451, 23776, 24076, 24156, 24206, 24501, 24826,
      24836, 24931, 24966, 24986, 25371, 25456, 25506, 25556,
      25566, 25651, 25856, 25966, 26001, 26021, 26111, 26256,
      26331, 26796, 26946, 27031, 27201, 27251, 27276, 27301,
      27361, 27456, 27896, 28061, 28221, 28331, 28426, 28431,
      28701, 28801, 29036, 29341, 29416, 29531, 29556, 29601,
      30256, 30476, 30616, 31066, 30681, 31081, 31226, 31356,
      31406, 32181, 32431, 32461, 32471, 32571, 32971, 33021,
      33081, 33261, 33301, 33486, 33561, 33791, 33846, 33871,
      33916, 33931, 34036, 34086, 34236,
    ];
  }

  /**
   * @todo Remove this when inline callout testing is done.
   */
  private function withCallout() {
    return [
      34276, 34241,
    ];
  }

}
