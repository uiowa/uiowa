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

  // @todo Remove this when video testing is done.
  public function query() {
    $query = parent::query();
    $query->condition('n.nid', $this->withVideos(), 'IN');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {

    // @TODO: THIS IS TEST CODE YOU NEED TO REMOVE IT IF YOU SEE THIS COMMENT!!!!!
    // Not even sure this works :( .
    // Comment it out if you need to.
    // vvvvv
    parent::prepareRow($row);
    $body = $row->getSourceProperty('body');
    if($body) {
      $this->logger->notice('!!!~~~ Body value is @body ~~~!!!', [
        '@body' => $body[0]['value'],
      ]);
    }
    if (preg_match('/drupal-media/', $body[0]['value']) > 0) {
      $this->logger->notice('!!!~~~ NID is @nid ~~~!!!', [
        '@nid' => $row->getSourceProperty('nid'),
      ]);
    }
    else {
      return FALSE;
    }
    // ^^^^^
    // THIS IS TEST CODE YOU NEED TO REMOVE IT IF YOU SEE THIS COMMENT!!!!!

    parent::prepareRow($row);

    $subhead = $row->getSourceProperty('field_subhead');
    if (!empty($subhead)) {
      $subhead = '<p class="uids-component--light-intro">' . $subhead[0]['value'] . '</p>';
      $body = $row->getSourceProperty('body');
      $body[0]['value'] = $subhead . $body[0]['value'];
      $row->setSourceProperty('body', $body);
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
      }
      elseif (in_array($filemime, ['video/oembed', 'application/octet-stream'])) {
        $body = $row->getSourceProperty('body');

        // Check to see if body has media in it, then set alignment.
        $video = '';
        if (preg_match('/drupal-media/', $body[0]['value']) > 0) {
          $video = $this->createVideo($media[0]['fid'], 'left');
        }
        else {
          $video = $this->createVideo($media[0]['fid']);
        }

        $body[0]['value'] = $video . $body[0]['value'];
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

  // @todo Remove this when video testing is done.
  private function withVideos() {
//    return [29416, 34426, 34406, 34361, 34351, 34331, 34326, 34266, 34121, 34096, 34086, 34036, 34021, 34011, 33916, 33856, 33851, 33836, 33821, 33551, 33401, 33416, 33371, 33361, 33366, 33346, 33336, 33276, 33236, 33221, 33176, 33171, 33166, 33081, 32916, 32891, 32816, 32826, 32811, 32806, 32781, 32786, 32776, 32791, 32771, 32766, 32756, 32746, 32661, 32571, 32561, 32496, 32461, 32446, 32341, 32261, 32276, 32221, 32196, 32166, 32161, 32111, 32131, 32126, 32106, 32081, 32061, 32066, 32021, 32016, 32031, 32011, 31986, 31976, 31981, 31966, 31951, 31946, 31931, 31916, 31891, 31866, 31836, 31791, 31786, 31756, 31651, 31591, 31586, 31526, 31481, 31476, 31396, 31356, 31326, 31206, 31306, 31256, 31261, 31156, 31136, 31086, 31036, 30951, 31026, 31006, 31011, 30956, 30936, 30946, 30916, 30901, 30896, 29911, 30721, 30726, 29966, 30686, 30671, 30631, 30646, 30601, 30591, 30496, 30436, 30316, 30291, 30236, 30216, 30206, 30156, 30146, 30131, 30111, 30096, 30081, 30041, 29996, 30031, 30011, 29976, 29961, 29956, 29831, 29936, 29916, 29906, 29896, 29886, 29881, 29816, 29826, 29806, 29751, 29686, 29641, 29636, 29626, 29596, 29296, 29251, 29261, 29291, 29181, 29121, 29166, 29176, 29311, 29126, 29116, 29076, 29081, 29096, 29051, 29036, 29011, 29021, 29016, 28986, 28976, 28946, 28936, 28921, 28836, 28911, 28876, 28866, 28776, 28686, 28696, 28731, 28586, 28601, 28596, 28536, 28446, 28481, 28466, 28431, 27611, 28111, 28251, 28176, 28186, 28181, 28006, 28101, 28076, 28016, 27841, 27971, 27966, 27896, 27906, 27876, 27851, 27816, 27536, 27691, 27456, 27466, 27261, 27031, 27361, 27366, 27386, 27331, 27241, 27231, 27116, 27136, 27131, 27101, 27126, 27096, 27051, 27061, 26996, 26981, 26991, 26856, 26836, 26821, 26736, 26741, 26681, 26661, 26671, 26656, 26646, 26481, 26511, 26446, 26431, 26356, 26346, 26291, 26301, 26281, 26241, 26131, 26191, 26166, 26101, 25986, 25936, 25931, 25926, 25871, 25811, 25426, 25716, 25696, 25711, 25701, 25661, 25641, 25651, 25581, 25586, 25671, 25601, 25576, 25556, 25516, 25526, 25496, 25491, 25461, 25416, 25406, 25361, 25331, 25286, 25276, 25246, 25226, 25251, 25186, 25126, 25041, 25036, 25011, 24981, 24966, 24901, 24826, 24836, 24821, 24811, 24726, 24716, 24711, 24721, 24666, 24476, 24316, 24421, 24416, 24331, 24296, 24306, 24286, 24271, 24276, 24246, 24236, 24211, 24206, 24201, 24166, 24146, 24136, 24116, 24031, 24111, 24076, 24121, 24026, 24011, 24021, 24046, 24016, 23986, 23871, 23851, 23791, 23751, 23796, 23786, 23761, 23736, 23716, 23586, 23691, 23401, 23596, 23641, 23591, 23491, 23526, 23531, 23516, 23521, 23481, 23476, 23471, 23461, 23411, 23366, 23361, 23351, 23336, 23311, 23306, 23246, 23226, 23076, 23196, 23186, 23176, 23151, 23111, 23116, 23106, 23091, 23031, 22971, 22946, 22941, 22891, 22926, 22856, 22651, 22726, 22696, 22691, 22656, 22661, 22586, 22546, 22491, 22471, 27601, 22406, 22381, 22376, 22351, 22346, 22181, 22296, 22276, 22241, 22231, 22151, 22131, 22096, 22036, 22026, 22011, 21836, 21821, 21611, 21641, 21581, 21651, 21646, 21601, 21466, 21451, 21396, 21316, 21311, 21281, 21256, 21201, 21186, 21151, 21101, 21131, 21116, 21106, 21081, 21051, 21011, 20976, 20996, 20806, 20797, 20781, 20676, 20726, 20716, 20666, 20691, 20661, 20576, 20571, 20536, 20541, 20421, 20241, 20486, 20461, 20431, 20426, 20416, 20391, 20291, 20266, 20186, 20126, 20136, 20116, 20006, 19946, 19931, 19891, 19721, 19626, 19661, 19606, 19611, 19046, 19546, 19571, 19556, 19551, 19441, 19431, 19416, 19351, 19301, 19276, 19146, 19006, 19126, 19021, 18991, 18951, 18946, 18916, 18896, 18856, 18841, 18816, 18646, 18571, 18596, 18681, 18656, 17941, 18491, 18462, 18361, 18401, 17591, 18371, 18301, 18281, 18276, 18156, 18201, 18171, 18141, 18136, 17976, 18106, 18041, 17996, 17826, 17821, 17466, 17836, 17816, 17791, 17726, 17711, 17681, 17636, 17616, 17516, 17606, 17396, 17210, 17476, 17441, 17401, 17256, 17241, 17218, 17213, 17150, 17146, 17152, 17140, 17202, 17147, 17156, 17139, 17136, 17133, 17108, 17099, 17083, 17051, 17027, 17032, 17037, 17028, 17014, 17016, 17011, 17013, 16987, 16933, 16983, 16963, 16949, 16931, 16905, 16940, 16916, 16840, 16914, 16910, 16926, 16925, 16923, 16909, 16894, 16884, 16888, 16904, 16895, 16883, 16882, 16881, 16871, 16879, 16838, 16827, 16811, 16806, 16796, 16782, 16783, 16765, 16753, 16756, 16738, 16717, 16721, 16720, 16718, 16685, 16698, 16681, 16669, 16655, 16648, 16634, 16622, 16620, 16574, 16602, 16604, 16249, 16593, 16581, 16577, 16566, 16552, 16515, 16510, 16508, 16507, 16465, 16445, 16457, 16448, 16430, 16429, 16387, 16419, 16400, 16372, 16381, 16385, 16341, 16375, 16366, 16331, 16292, 16232, 16298, 16289, 16290, 16302, 16308, 16305, 16293, 16285, 16245, 16281, 16131, 16236, 16240, 16231, 16107, 16222, 16181, 16157, 16141, 16210, 16097, 16200, 16199, 16188, 16187, 16177, 16154, 16146, 16037, 16138, 16125, 16094, 16095, 15957, 16084, 16012, 15944, 16093, 16046, 15886, 16021, 16016, 15955, 15956, 15870, 15943, 15874, 15921, 15910, 15894, 15878, 15883, 15871, 15856, 15836, 15738, 15727, 15710, 15712, 15613, 15633, 15640, 15635, 15631, 15610, 15607, 15606, 15597, 15596, 15589, 15587, 15583, 15579, 15567, 15559, 15549, 15542, 15521, 15515, 15501, 15486, 15482, 15475, 15474, 15461, 15460, 15443, 15440, 15439, 15437, 15434, 15421, 15419, 15410, 15391, 15389, 15347, 15322, 15309, 15315, 15301, 15296, 15211, 15195, 15175, 15168, 15139, 15124, 15068, 15067, 15045, 15029, 15016, 15013, 14999, 14984, 14982, 14970, 14969, 14961, 14957, 14881, 14866, 14571, 14537, 14544, 14538, 14535, 14101, 14110, 14105, 14102, 14083, 14066, 14031, 13992, 13980, 13990, 13982, 13981, 13970, 13938, 13861, 13806, 13759, 13758, 13739, 13669, 14123, 13588, 13560, 13540, 13513, 14811, 13382, 13350, 13351, 13229, 14164, 13311, 13027, 13017, 12993, 12978, 14151, 14138, 12820, 12824, 12852, 12764, 12743, 12727, 12659, 12625, 14684, 12510, 12490, 12477, 14772, 12431, 12688, 12364, 12361, 14657, 12317, 12302, 12256, 12230, 12195, 12154, 12146, 12127, 12110, 12102, 12063, 12003, 11964, 12693, 11786, 11789, 11734, 11738, 11710, 11641, 11621, 11607, 11527, 14629, 12701, 11453, 11446, 11822, 14523, 11287, 11277, 11278, 14313, 11261, 11247, 11210, 11213, 11197, 11185, 11823, 11129, 11114, 11083, 11039, 11012, 14597, 10879, 10852, 10831];
    return [14772, 14811, 14866, 14957, 14969, 14999, 15068, 15322, 15567, 15579, 15587, 15589, 15597, 16097, 16181, 16231, 17108, 17836, 18946, 19126, 20661, 21601, 22891]
  }

}
