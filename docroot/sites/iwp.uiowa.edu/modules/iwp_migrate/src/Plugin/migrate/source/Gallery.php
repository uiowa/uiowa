<?php

namespace Drupal\iwp_migrate\Plugin\migrate\source;

use Drupal\media\Entity\Media;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "iwp_gallery",
 *   source_module = "node"
 * )
 */
class Gallery extends BaseNodeSource {
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

    // Skip over the rest of the preprocessing, as it's not needed
    // for redirects. Also avoids duplicating the notices.
    // Return TRUE because the row should be created.
    if ($this->migration->id() === 'iwp_gallery_redirects') {
      return TRUE;
    }

    if ($media = $row->getSourceProperty('field_youtube_id')) {
      $id = $media[0]['value'];
      $entity_id = \Drupal::database()->select('media__field_media_oembed_video', 'm')
        ->fields('m', ['entity_id'])
        ->condition('m.field_media_oembed_video_value', $id, 'LIKE')
        ->execute()
        ->fetchField();
      if (!$entity_id) {
        $media = Media::create([
          'bundle' => 'remote_video',
          'name' => $row->getSourceProperty('title'),
          'field_media_oembed_video' => "https://www.youtube.com/watch?v={$id}",
        ]);
        if ($media->save()) {
          $entity_id = $media->id();
        }
      }
      $row->setSourceProperty('field_youtube_id', $entity_id);
    }

    if ($source_year = $row->getSourceProperty('field_media_year')) {
      // Lookup term reference id name.
      $tid = $source_year[0]['tid'];
      $year = \Drupal::database()->select('taxonomy_term_data', 't')
        ->fields('t', ['name'])
        ->condition('t.tid', $tid, '=')
        ->execute()
        ->fetchField();
      if ($year) {
        $row->setSourceProperty('field_media_year', $year);
      }
    }

    return TRUE;
  }

}
