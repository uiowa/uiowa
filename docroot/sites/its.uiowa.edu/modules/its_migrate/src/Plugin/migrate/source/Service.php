<?php

namespace Drupal\its_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "its_service",
 *   source_module = "node"
 * )
 */
class Service extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Make sure our nodes are retrieved in order,
    // and force a highwater mark of our last-most migrated node.
    $query->orderBy('nid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    $fee_info = $row->getSourceProperty('field_ic_fees');
    if (isset($fee_info)) {
      $fee_info[0]['format'] = 'minimal_plus';
      $row->setSourceProperty('field_ic_fees', $fee_info);
    }

    foreach ([
      'field_ic_category',
      'field_audience',
    ] as $source_field) {
      if ($values = $row->getSourceProperty($source_field)) {
        if (!isset($values)) {
          continue;
        }
        $tids = [];
        foreach ($values as $tid_array) {
          $tids[] = $tid_array['tid'];
        }
        // Fetch tag names based on TIDs from our old site.
        $tag_results = $this->select('taxonomy_term_data', 't')
          ->fields('t', ['name'])
          ->condition('t.tid', $tids, 'IN')
          ->execute();
        $new_tids = [];
        foreach ($tag_results as $result) {
          $tag_name = $result['name'];
          $new_tids[] = $this->fetchTag($tag_name, $row);
        }
        $row->setSourceProperty("{$source_field}_processed", $new_tids);
      }
    }

//    // Process the primary media field.
//    $media = $row->getSourceProperty('field_primary_media');
//    if (!empty($media)) {
//      // Check if it's a video or image.
//      $filemime = $this->select('file_managed', 'fm')
//        ->fields('fm', ['filemime'])
//        ->condition('fm.fid', $media[0]['fid'], '=')
//        ->execute()
//        ->fetchField();
//      // If it's an image, we can handle it like normal.
//      if (str_starts_with($filemime, 'image')) {
//        $fid = $this->processImageField($media[0]['fid'], $media[0]['alt'], $media[0]['title']);
//        $row->setSourceProperty('field_primary_media', $fid);
//      }
//      elseif (in_array($filemime, ['video/oembed', 'application/octet-stream'])) {
//        $body = $row->getSourceProperty('body');
//        $body[0]['value'] = $this->createVideo($media[0]['fid']) . $body[0]['value'];
//        $row->setSourceProperty('body', $body);
//      }
//    }
//
    // Replace inline files and images in the body,
    // and set for placement in the body and teaser fields.
//    $body = $row->getSourceProperty('body');
//    if (!empty($body)) {
//      $this->viewMode = 'medium__no_crop';
//      $this->align = 'left';
//      // Search for D7 inline embeds and replace with D8 inline entities.
//      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);
//      // Set the format to filtered_html while we have it.
//      $body[0]['format'] = 'filtered_html';
//
//      // Check for captions in the old format, and if found,
//      // manually insert them into the drupal-media element.
//      $body[0]['value'] = preg_replace_callback('%<div class=\"(image|video)-(.*?)-(.*?)\">(<drupal-media.*?)><\/drupal-media>(.*?)<\/div>%is', [
//        $this,
//        'captionReplace',
//      ], $body[0]['value']);
//      // Check for callouts in the source,
//      // and construct the proper format for the destination.
//      $body[0]['value'] = preg_replace_callback('|<div class=\"(.*?)-callout\">(.*?)<\/div>|is', [
//        $this,
//        'calloutReplace',
//      ], $body[0]['value']);
//
//      // Remove empty <p> tags as well.
//      $body[0]['value'] = preg_replace('@<p>(\s?|&nbsp;)<\/p>@is', '', $body[0]['value']);
//
//      $row->setSourceProperty('body', $body);
//      // Extract the summary.
//      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
//    }
//
//    // Truncate the featured image caption, if needed,
//    // and add a message to the migrate table for future followup.
//    $caption = $row->getSourceProperty('field_primary_media_caption');
//    if (!empty($caption)) {
//      if (strlen($caption[0]['value']) > 255) {
//        $message = 'Field image caption truncated. Original caption was: ' . $caption[0]['value'];
//        // Need to limit to '252' to account for the three charaacter
//        // ellipsis that will be added.
//        $caption[0]['value'] = $this->extractSummaryFromText($caption[0]['value'], 252);
//        $row->setSourceProperty('field_primary_media_caption', $caption);
//        // Add a message to the migration that can be queried later.
//        // The following query can then be used:
//        // "SELECT migrate_map_now_news_feature.destid1 AS NODE_ID,
//        // migrate_message_now_news_feature.message AS MESSAGE
//        // FROM migrate_map_now_news_feature JOIN
//        // migrate_message_now_news_feature ON
//        // migrate_map_now_news_feature.source_ids_hash =
//        // migrate_message_now_news_feature.source_ids_hash;".
//        $this->migration
//          ->getIdMap()
//          ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
//      }
//    }
//
//    // If there's a byline, prepend 'By: ' to it
//    // since it is being added to the article source field,
//    // and would otherwise be unlabeled.
//    $byline = $row->getSourceProperty('field_by_line');
//    if (!empty($byline)) {
//      $byline = 'Written by: ' . $byline[0]['value'];
//      $row->setSourceProperty('field_by_line', $byline);
//    }

    if($row->getSourceProperty('title') === 'Wiki') {
      $foo = 'bar';
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

    // Remove anything after the first '<br>',
    // as it is not in the same '<strong>' group
    // as the ones on the first line.
    $headline_match_string = preg_replace('%(<br>|<br \/>|<br\/>|<ul>).*%is', '', $match[2]);

    // Look for a headline to use in the callout, which are bolded strings
    // at the start of the callout. Also look for any additional
    // line breaks. Like before, here they are unnecessary.
    $headline = '';
    if (preg_match_all("|<strong>(.*?)<\/strong>(<br>)*|is", $headline_match_string, $headline_matches)) {
      // Build the headline if we found one.
      $headline_classes = implode(' ', [
        'headline',
        'block__headline',
        'headline--serif',
        'headline--underline',
        'headline--center',
      ]);

      // If there are multiple <strong>'s, then we need to concatenate them.
      $headline_text = '';
      foreach ($headline_matches[1] as $value) {
        $headline_text .= $value;
        // If we're adding the headline separately,
        // remove it from the rest of the text, so we don't duplicate.
        $match[2] = str_replace('<strong>' . $value . '</strong>', '', $match[2]);
      };
      $headline = '<h4 class="' . $headline_classes . '">';
      $headline .= '<span class="headline__heading">';
      $headline .= $headline_text;
      $headline .= '</span></h4>';
    }

    // Remove all leading and trailing 'br' tags.
    $match[2] = preg_replace("%^(<br>|<br \/>|<br\/>\s)*%is", '', $match[2], 1);
    $match[2] = preg_replace("%(<br>|<br \/>|<br\/>$|\s)*%is", '', $match[2], 1);

    // Build the callout wrapper and return.
    // We're defaulting to medium size, but taking the
    // alignment from the source.
    $wrapper_classes = 'block--word-break callout bg--gray inline--size-small inline--align-' . $match[1];
    return '<div class="' . $wrapper_classes . '">' . $headline . $match[2] . '</div>';
  }

  /**
   * Helper function to fetch existing tags.
   */
  private function fetchTag($tag_name, $row) {
    // Check if we already have the tag in the destination.
    $result = \Drupal::database()
      ->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid'])
      // @todo Add another conditional to match the proper vocabulary,
      //   in case there are duplicate terms across different vocabs.
      ->condition('t.name', $tag_name, '=')
      ->execute()
      ->fetchField();
    if ($result) {
      return $result;
    }
    // If we didn't have the tag already,
    // add a notice to the migration, and return a null.
    $message = 'Taxonomy term failed to migrate. Missing term was: ' . $tag_name;
    $this->migration
      ->getIdMap()
      ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
    return FALSE;
  }
}
