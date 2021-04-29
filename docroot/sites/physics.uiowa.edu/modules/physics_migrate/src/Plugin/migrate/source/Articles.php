<?php

namespace Drupal\physics_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "physics_articles",
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
    $body = $row->getSourceProperty('field_clas_news_description');

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
          if (strpos($href, '/node/') === 0 || stristr($href, 'physics.uiowa.edu/node/')) {
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
    $tags = [];

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
              '@term' => $ref['tid'],
              '@article' => $row->getSourceProperty('title'),
            ]);
          }
        }
      }
    }

    $row->setSourceProperty('tags', $tags);

    // Process the Link directly to source field if external link is provided.
    $extlink = $row->getSourceProperty('field_clas_news_link');

    if (!empty($extlink)) {
      $row->setSourceProperty('field_article_source_link_direct', 1);
    }
    else {
      $row->setSourceProperty('field_article_source_link_direct', 0);
    }

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
      6 => 'Space Physics',
      5 => 'Plasma Physics',
      13 => 'Photonics and Quantum Electronics',
      11 => 'Nonlinear Dynamics',
      9 => 'Mathematical Physics',
      2 => 'Condensed Matter and Materials Physics',
      8 => 'Atmospheric and Environmental Physics',
      10 => 'Medical and Biomedical Physics',
      1 => 'Astronomy and Astrophysics',
      1211 => 'Astronomy and Astrophysics',
      1216 => 'Astronomy and Astrophysics',
      1221 => 'Astronomy and Astrophysics',
      1226 => 'Astronomy and Astrophysics',
      1231 => 'Astronomy and Astrophysics',
      1236 => 'Astronomy and Astrophysics',
      12 => 'Photonics and Quantum Electronics',
      7 => 'Nuclear and Particle Physics',
      3 => 'Nuclear and Particle Physics',
    ];

    return isset($map[$nid]) ? $map[$nid] : FALSE;
  }

}
