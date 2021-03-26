<?php

namespace Drupal\pharmacy_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "pharmacy_articles",
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
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));

      // Unlink anchors in body from articles before 2016.
      $created_year = date('Y', $row->getSourceProperty('created'));

      if ($created_year < 2016) {
        $doc = Html::load($body[0]['value']);
        $links = $doc->getElementsByTagName('a');

        foreach ($links as $link) {
          $text = $doc->createTextNode($link->nodeValue);
          $link->parentNode->replaceChild($text, $link);
        }

        $doc->saveHTML();
        $html = Html::serialize($doc);
        $body[0]['value'] = $html;
      }

      $row->setSourceProperty('body', $body);
    }

    // Process the image field.
    $image = $row->getSourceProperty('field_article_image');

    if (!empty($image)) {
      $mid = $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']);
      $row->setSourceProperty('field_article_image_mid', $mid);
    }

    // Create combined array of taxonomy terms to map to tags.
    $tags = [];

    $reference_fields = [
      'field_department_multi',
      'field_audience_multi',
      'field_news_category',
    ];

    foreach ($reference_fields as $field_name) {
      if ($refs = $row->getSourceProperty($field_name)) {
        foreach ($refs as $ref) {
          $tags[] = $ref['tid'];
        }
      }
    }

    $row->setSourceProperty('tags', $tags);

    return TRUE;
  }

}
