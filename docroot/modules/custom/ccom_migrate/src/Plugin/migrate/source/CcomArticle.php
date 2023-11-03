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

    // @todo Create Source field info from field_article_external_url and field_article_iowanow_url.
    // Process the gallery images from field_article_gallery.
    $gallery = $row->getSourceProperty('field_article_gallery');
    if (!empty($gallery)) {

      // @todo Check how the gallery images are stored in CCOM sites,
      //   and adjust the following.
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

    // @todo Look at what exists in source metatags and map.
    // If a date is specified in field_date,
    // convert to a timestamp and map to created.
    $date = $row->getSourceProperty('field_date');
    if (!empty($date)) {
      $row->setSourceProperty('created', strtotime($date));
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
