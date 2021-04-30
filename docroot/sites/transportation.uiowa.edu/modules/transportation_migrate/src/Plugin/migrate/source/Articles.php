<?php

namespace Drupal\transportation_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "transportation_articles",
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
          if (strpos($href, '/node/') === 0 || stristr($href, 'transportation.uiowa.edu/node/')) {
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

    // Create combined array of taxonomy terms to map to tags.
    // ALL articles will receive a default news-ParkTransit tag
    // which has a TID of 46.
    $tags = [46];

    // @todo Update with the correct field(s).
    $reference_fields = [
      'field_clas_news_tags',
    ];

    // Lookup and store new term given a TID on the old site.
    foreach ($reference_fields as $field_name) {
      if ($refs = $row->getSourceProperty($field_name)) {
        foreach ($refs as $ref) {
          if ($lookup = $this->manualLookup($ref['tid'])) {
            $tags[] = $lookup;
            $this->logger->info('Replaced term @tid in article @article.', [
              '@tid' => $ref['tid'],
              '@article' => $row->getSourceProperty('title'),
            ]);
          }
        }
      }
    }

    $row->setSourceProperty('tags', $tags);

    return TRUE;
  }

  /**
   * Return the taxonomy term given a TID on the old site.
   *
   * @param int $tid
   *   The term ID.
   *
   * @return false|string
   *   The new term or FALSE if not in the map.
   */
  protected function manualLookup($tid) {
    $map = [
      // Transit Study 2019-2020 => news-CAMBUS.
      96 => 51,
      // Cambus => news-CAMBUS.
      6 => 51,
      // UnPark Yourself => news-AlternativeTransportation.
      66 => 76,
      // Walking => news-BikeWalk.
      8 => 101,
      // University Vehicles => news-Fleet.
      26 => 36,
      // Parking => news-Parking.
      5 => 81,
      // Rideshare => news-Rideshare.
      32 => 86,
      // U-PASS => news-UPASS (labelled BusPass).
      55 => 91,
      // All terms should get news-ParkTransit as well.
      0 => 46,
    ];

    return isset($map[$tid]) ? $map[$tid] : FALSE;
  }

}
