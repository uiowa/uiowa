<?php

namespace Drupal\iowaprotocols_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

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
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

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

    return TRUE;
  }

}
