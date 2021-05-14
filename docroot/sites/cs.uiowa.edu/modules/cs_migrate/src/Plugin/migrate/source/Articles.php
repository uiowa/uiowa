<?php

namespace Drupal\cs_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "cs_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
    $query->fields('alias', ['alias']);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['alias'] = $this->t('The URL alias for this node.');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);
    $body = $row->getSourceProperty('body');

    if (!empty($body)) {
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineImages($body[0]['value'], '/sites/cs.uiowa.edu/files/', 'medium__no_crop');

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));

      // Parse links.
      $doc = Html::load($body[0]['value']);
      $links = $doc->getElementsByTagName('a');
      $i = $links->length - 1;
      $created_year = date('Y', $row->getSourceProperty('created'));

      while ($i >= 0) {
        $link = $links->item($i);
        $href = $link->getAttribute('href');

        // Unlink anchors in body from articles before 2016.
        if ($created_year < 2016) {
          $text = $doc->createTextNode($link->nodeValue);
          $link->parentNode->replaceChild($text, $link);
          $doc->saveHTML();
        }
        else {
          if (strpos($href, '/node/') === 0 || stristr($href, 'cs.uiowa.edu/node/')) {
            // Report out any internal links which may need updating.
            $this->logger->notice('Unable to replace internal link @link in article @article.', [
              '@link' => $href,
              '@article' => $row->getSourceProperty('title'),
            ]);
          }
        }

        $i--;
      }

      $html = Html::serialize($doc);
      $body[0]['value'] = $html;

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);
    }

    // Process the image field.
    $image = $row->getSourceProperty('field_image');

    if (!empty($image)) {
      $mid = $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']);
      $row->setSourceProperty('field_article_image_mid', $mid);
    }

    // Process the Link directly to source field if external link is provided.
    $extlink = $row->getSourceProperty('field_news_external_link');

    if (!empty($extlink)) {
      $row->setSourceProperty('field_article_source_link_direct', 1);
    }
    else {
      $row->setSourceProperty('field_article_source_link_direct', 0);
    }

    $this->fetchImageGallery($row);

    return TRUE;
  }

  /**
   * Fetch our image gallery and append to the body.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration row result.
   */
  public function fetchImageGallery(Row &$row) {
    $nid = $row->getSourceProperty('nid');
    // Grab info on all images attached in the gallery.
    $results = $this->select('field_data_field_image_gallery', 't')
      ->fields('t', [
        'field_image_gallery_fid',
        'field_image_gallery_title',
        'field_image_gallery_alt',
      ])
      ->condition('entity_id', $nid, '=')
      ->execute()
      ->fetchAllAssoc('field_image_gallery_fid');
    // Go ahead and pop out if we don't have any images to append
    // to avoid creating media manager, and possibly other processes.
    if (empty($results)) {
      return;
    }
    $media_manager = \Drupal::service('entity_type.manager')
      ->getStorage('media');
    $body = $row->getSourceProperty('body');
    foreach ($results as $fid => $meta) {
      // For each image, processImageField will check if it exists
      // and return the media id we need to place inline,
      // or download the file and create a new media entity if needed.
      $mid = $this->processImageField($fid, $meta['field_image_gallery_alt'], $meta['field_image_gallery_title']);
      if ($mid) {
        // Unfortunately, we need the uuid, not the mid.
        $uuid = $media_manager->load($mid)->uuid();
        // Defaulting to center align for all image gallery images.
        $media_render = $this->constructInlineEntity($uuid, 'center', 'medium__no_crop');
        $body[0]['value'] = $body[0]['value'] . $media_render;
      }
    }
    $row->setSourceProperty('body', $body);
  }

}
