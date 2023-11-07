<?php

namespace Drupal\ccom_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "ccom_article",
 *   source_module = "node"
 * )
 */
class CcomArticle extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Make sure our nodes are retrieved in order
    // for ease of debugging.
    $query->orderBy('nid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Create tags from field_tags source field.
    $values = $row->getSourceProperty('field_tags');
    $tag_tids = [];
    foreach ($values as $tid_array) {
      $tag_tids[] = $tid_array['tid'];
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

    // Create Source field info from
    // field_article_external_url and field_article_iowanow_url.
    // If they are both filled, default to the Iowa Now source.
    $external_url = $row->getSourceProperty('field_article_external_url');
    $iowanow_url = $row->getSourceProperty('field_article_iowanow_url');
    if (!empty($iowanow_url)) {
      // Set the title manually if it's Iowa Now,
      // since it's often not defined on the source.
      $source = $iowanow_url;
      $source[0]['title'] = 'Iowa Now';
    }
    else {
      $source = $external_url;
    }
    $row->setSourceProperty('source', $source);

    // Process the gallery images from field_article_gallery.
    $gallery = $row->getSourceProperty('field_article_gallery');
    if (!empty($gallery)) {
      $new_images = [];
      foreach ($gallery as $gallery_image) {
        $new_images[] = $this->processImageField($gallery_image['field_article_gallery_fid'], $gallery_image['field_article_gallery_alt'], $gallery_image['field_article_gallery_title'], $gallery_image['field_article_gallery_title']);
      }
      $row->setSourceProperty('gallery', $new_images);
    }

    // Look at what exists in source metatags and map.
    if ($metatags = $row->getSourceProperty('pseudo_metatag_entities')) {
      $unserialized = unserialize($metatags);
      foreach ($unserialized as $item => $value) {
        // Add a notice and log a message for the
        // metatags that aren't being mapped.
        $value = $value['value'];
        $message = "Metatag $item: $value not migrated.";
        $this->migration
          ->getIdMap()
          ->saveMessage(['nid' => $row->getSourceProperty('nid')], $message);
        $this->logger->notice('Metatag @item: @value not migrated for node: @nid', [
          '@item' => $item,
          '@value' => $value,
          '@nid' => $row->getSourceProperty('nid'),
        ]);
      }
    }
    // If a date is specified in field_date,
    // convert to a timestamp and map to created.
    $date = $row->getSourceProperty('field_date');
    if (!empty($date) && isset($date[0]['value'])) {
      $row->setSourceProperty('created', strtotime($date[0]['value']));
    }

    // Replace inline files and images in the body,
    // and set for placement in the body and teaser fields.
    $body = $row->getSourceProperty('body');
    if (!empty($body)) {
      $this->viewMode = 'large';
      $this->align = 'left';
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Check for prepended dates in the body field.
      if (preg_match('%^<p>((January|February|March|April|May|June|July|August|September|October|November|December) \d{1,2}, \d{4})<\/p>%', $body[0]['value'], $matches)) {
        // If we didn't have a specified date, but had a prepended date,
        // use this one over the article created date.
        if (!$date) {
          $row->setSourceProperty('created', strtotime($matches[1]));
        }
        // Remove the now erroneous date.
        $body[0]['value'] = preg_replace('%^<p>(January|February|March|April|May|June|July|August|September|October|November|December) \d{1,2}, \d{4}<\/p>%', '', $body[0]['value']);
      }

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);
    }

    return TRUE;
  }

  /**
   * Helper function to check for existing tags and create if they don't exist.
   */
  private function createTag($tag_name, $row) {
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

}
