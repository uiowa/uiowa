<?php

namespace Drupal\iowaprotocols_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "protocol",
 *   source_module = "node"
 * )
 */
class Protocol extends BaseNodeSource {

  use ProcessMediaTrait;

  /**
   * Tag-to-name mapping for keywords.
   *
   * @var array
   */
  protected $tagMapping;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Establish an array to eventually map to field_tags.
    $tids = [];

    // Set our tagMapping if it's not already.
    if (empty($this->tagMapping)) {
      $this->tagMapping = \Drupal::database()
        ->select('taxonomy_term_field_data', 't')
        ->fields('t', ['name', 'tid'])
        ->condition('t.vid', 'tags', '=')
        ->execute()
        ->fetchAllKeyed();
    }

    // Process the gallery images from field_article_gallery.
    $gallery = $row->getSourceProperty('field_basic_page_gallery');
    if (!empty($gallery)) {
      $new_images = [];
      foreach ($gallery as $gallery_image) {
        $new_images[] = $this->processImageField(
          $gallery_image['fid'],
          $gallery_image['alt'],
          $gallery_image['title'],
          $gallery_image['title']
        );
      }
      $row->setSourceProperty('gallery', $new_images);
    }

    // Replace inline files and images in the body,
    // and set for placement in the body and teaser fields.
    $body = $row->getSourceProperty('body');
    if (!empty($body)) {
      $this->viewMode = 'large';
      $this->align = 'left';
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);
    }

    // Process the gallery images from field_article_gallery.
    $category = $row->getSourceProperty('field_category')[0]["value"];
    if (!empty($category)) {
      $tid = $this->createTag($category);
      $tids[] = $tid;
    }

    // Send all final tids to field_tags.
    if (!empty($tids)) {
      $row->setSourceProperty('tags', $tids);
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

}
