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
 *   id = "now_achievement",
 *   source_module = "node"
 * )
 */
class Achievement extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Process the image field.
    $image = $row->getSourceProperty('field_image');

    if (!empty($image)) {
      $fid = $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']);
      $row->setSourceProperty('field_image', $fid);
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
    $category = $row->getSourceProperty('field_achievement_category')[0]['value'];
    if (!empty($category)) {
      $tid = $this->createTag($category);
      $row->setSourceProperty('tags', $tid);
    }
    $news_from = $row->getSourceProperty('field_news_from')[0]['value'];
    if (!empty($news_from)) {
      $tid = $this->createTag($news_from);
      $row->setSourceProperty('tags', $tid);
    }
    $news_about = $row->getSourceProperty('field_news_about')[0]['value'];
    if (!empty($news_about)) {
      $tid = $this->createTag($news_about);
      $row->setSourceProperty('tags', $tid);
    }
    $news_for = $row->getSourceProperty('field_news_for')[0]['value'];
    if (!empty($news_for)) {
      $tid = $this->createTag($news_for);
      $row->setSourceProperty('tags', $tid);
    }
    $news_keywords = $row->getSourceProperty('field_news_keywords')[0]['value'];
    if (!empty($news_keywords)) {
      $tid = $this->createTag($news_keywords);
      $row->setSourceProperty('tags', $tid);
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
