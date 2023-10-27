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
 *   id = "now_in_the_news",
 *   source_module = "node"
 * )
 */
class InTheNews extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // There are two test articles and one with a malformed
    // source reference that we are skipping.
    $query->condition('nr.nid', [15505, 20961, 26976], 'NOT IN');
    return $query;
  }

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
        ->execute()
        ->fetchField();
      // If it's an image, we can handle it like normal.
      if (str_starts_with($filemime, 'image')) {
        $fid = $this->processImageField($media[0]['fid'], $media[0]['alt'], $media[0]['title']);
        $row->setSourceProperty('field_primary_media', $fid);
      }
      elseif (in_array($filemime, ['video/oembed', 'application/octet-stream'])) {
        $body = $row->getSourceProperty('body');
        $body[0]['value'] = $this->createVideo($media[0]['fid']) . $body[0]['value'];
        $row->setSourceProperty('body', $body);
      }
    }

    $source_collection = $row->getSourceProperty('field_sources');
    if (!empty($source_collection)) {
      // Even if there are multiple sources, we can grab the first
      // and ignore the rest.
      $source_id = $source_collection[0]['value'];
      $url = $this->select('field_data_field_url', 'url')
        ->fields('url', ['field_url_url'])
        ->condition('url.entity_id', $source_id, '=')
        ->execute()
        ->fetchField();
      $url = $this->fixUrls($url);
      $query = $this->select('field_data_field_source', 'source');
      $query->leftJoin('taxonomy_term_data', 't', 't.tid = source.field_source_tid');
      $source_name = $query->fields('t', ['name'])
        ->condition('source.revision_id', $source_id, '=')
        ->execute()
        ->fetchField();
      $row->setSourceProperty('source_name', $source_name);
      $row->setSourceProperty('source_url', $url);
    }

    // We use the source link to determine if this is a spotlight
    // or in the news.
    $article_type = 'in-the-news';
    if (isset($url) && str_contains($url, 'uiowa.edu')) {
      $article_type = 'ui-spotlight';
    }
    $row->setSourceProperty('article_type', $article_type);

    // If we have an original publication date,
    // grab the datetime string and convert it to a timestamp,
    // then manually construct our smart_date info.
    $original_pub_date = $row->getSourceProperty('field_original_pub_date');
    if (!empty($original_pub_date)) {
      $timestamp = strtotime($original_pub_date[0]['value']);
      $row->setSourceProperty('field_original_pub_date', [
        0 => [
          'value' => $timestamp,
          'end_value' => $timestamp + 86340,
          'duration' => '0',
          'rrule' => NULL,
          'rrule_index' => NULL,
          'timezone' => '',
        ],
      ]);
    }

    // Map various old fields into Tags.
    $tag_tids = [];
    foreach ([
      'field_news_from',
      'field_news_about',
      'field_news_for',
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
        $tid = $this->createTag($tag_name, $row);

        // Add the mapped TID to match our tag name.
        if ($tid) {
          $tags[] = $tid;
        }

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
      $row->setSourceProperty('body', $body);
      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    return TRUE;
  }

  /**
   * Helper function to check for existing tags and create if they don't exist.
   */
  private function createTag($tag_name) {
    // Check if we already have the tag in the destination.
    $result = \Drupal::database()
      ->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid'])
      ->condition('t.vid', 'tags', '=')
      ->condition('t.name', $tag_name, '=')
      ->execute()
      ->fetchField();
    if ($result) {
      return $result;
    }
    // If we didn't have the tag already,
    // then create a new tag and return its id.
    $term = Term::create([
      'name' => $tag_name,
      'vid' => 'tags',
    ]);
    if ($term->save()) {
      return $term->id();
    }

    // If we didn't save for some reason, add a notice
    // to the migration, and return a null.
    $message = 'Taxonomy term failed to migrate. Missing term was: ' . $tag_name;
    $this->migration
      ->getIdMap()
      ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
    return FALSE;
  }

  /**
   * Helper function to fix a few funky URLs.
   */
  private function fixUrls($url) {
    $map = [
      'bhttp://healthland.time.com/2012/11/07/researchers-solve-the-mystery-of-childs-illness/' => 'https://healthland.time.com/2012/11/08/researchers-solve-the-mystery-of-childs-illness/',
      'bit.ly/1fYA2JK' => 'https://nonpareilonline.com/business/whitcher-takes-over-iowa-legal-aid/article_ce43df0a-2b33-11e5-8015-ab51eac75ae4.html',
      'cnn.com' => 'https://www.cnn.com/',
    ];
    return (isset($map[$url])) ? $map[$url] : $url;
  }

}
