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

    // Create Source field info from
    // field_article_external_url and field_article_iowanow_url.
    // If they are both filled, default to the Iowa Now source.
    $external_url = $row->getSourceProperty('field_article_external_url');
    $iowanow_url = $row->getSourceProperty('field_article_iowanow_url');
    if (!empty($iowanow_url)) {
      // Set the title manually if it's Iowa Now,
      // since it's often not defined on the source.
      $source = [
        'url' => $iowanow_url['url'],
        'title' => 'Iowa Now',
      ];
    }
    elseif (!empty($external_url)) {
      $source = $external_url;
    }
    if (isset($source)) {
      $row->setSourceProperty('source', $source);
    }

    // Process the gallery images from field_article_gallery.
    $gallery = $row->getSourceProperty('field_article_gallery');
    if (!empty($gallery)) {
      $new_images = [];
      foreach ($gallery as $gallery_image) {
        $new_images[] = $this->processImageField($gallery_image['field_article_gallery_fid'], $gallery_image['field_article_gallery_alt'], $gallery_image['field_article_gallery_title'], $gallery_image['field_article_gallery_title']);
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
