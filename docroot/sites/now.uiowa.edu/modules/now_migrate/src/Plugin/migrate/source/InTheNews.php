<?php

namespace Drupal\now_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;
use function _PHPStan_4b01b3801\React\Promise\Stream\first;

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
      $source_id = $source_collection[0]['revision_id'];
      $url = $this->select('field_data_field_url', 'url')
        ->fields('url', ['field_url_url'])
        ->condition('url.revision_id', $source_id, '=')
        ->execute()
        ->fetchField();
      // @todo This could be combined into a single call.
      $tid = $this->select('field_data_field_source', 'source')
        ->fields('source', ['field_source_tid'])
        ->condition('source.revision_id', $source_id, '=')
        ->execute()
        ->fetchField();
      $source_name = $this->select('taxonomy_term_data', 't')
        ->fields('t', ['name'])
        ->condition('t.tid', $tid, '=')
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

    if (!empty($image)) {
      $fid = $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']);
      $row->setSourceProperty('field_image', $fid);
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
