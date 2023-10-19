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
      $this->viewMode = 'large__no_crop';
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Parse links.
      $doc = Html::load($body[0]['value']);
      $links = $doc->getElementsByTagName('a');
      $i = $links->length - 1;

      while ($i >= 0) {
        $link = $links->item($i);
        $href = $link->getAttribute('href');

        if (strpos($href, '/node/') === 0 || stristr($href, 'transportation.uiowa.edu/node/')) {
          $this->logger->notice('Unable to replace internal link @link in article @article.', [
            '@link' => $href,
            '@article' => $row->getSourceProperty('title'),
          ]);
        }
        // Update URL path for hardcoded links.
        if ($href === 'https://transportation.uiowa.edu/transit-services-persons-disabilities-bionic-bus') {
          $this->logger->notice('Replaced hardcoded link @link in article @article.', [
            '@link' => $href,
            '@article' => $row->getSourceProperty('title'),
          ]);
          // Replace with internal link to /cambus/bionic-bus.
          $link->setAttribute('href', '/node/376');
          $link->parentNode->replaceChild($link, $link);
        }
        if ($href === 'https://transportation.uiowa.edu/transit') {
          $this->logger->notice('Replaced hardcoded link @link in article @article.', [
            '@link' => $href,
            '@article' => $row->getSourceProperty('title'),
          ]);
          // Replace with the internal link to /cambus/transit.
          $link->setAttribute('href', '/node/371');
          $link->parentNode->replaceChild($link, $link);
        }

        $i--;
      }

      // Time to get rid of duplicate datelines.
      // First lets get our paragraphs.
      $paragraphs = $doc->getElementsByTagName('p');
      // Looping through forward this time,
      // because we won't remove it yet and this lets us find the first
      // instance first before going further.
      foreach ($paragraphs as $paragraph) {
        $text = $paragraph->textContent;
        // Check for a Month DD, YYYY format
        // Allowing for extra spaces, but nothing else in the line.
        // Assuming the month will always be spelled out in full here,
        // which in investigating appears to be the case.
        if (preg_match('@(?:\s|&nbsp;)*(?:January|February|March|April|May|June|July|August|September|October|November|December)(?:\s|&nbsp;)*[0-3]?[0-9],(?:\s|&nbsp;)+\d{4}(?:\s|&nbsp;)*@i', $text)) {
          // Mark it to remove, and exit our looping.
          $to_remove = $paragraph;
          break;
        }
      }
      if (isset($to_remove)) {
        // Can't remove directly, so grab the parent
        // and use it to remove our node.
        $to_remove->parentNode->removeChild($to_remove);
      }

      // Now time to grab the first headline and replace the title with it.
      // Assuming it should always be the first <h2> in the body content.
      $headline = $doc->getElementsByTagName('h2')->item(0);
      if (isset($headline)) {
        // Replace the title with its text.
        $row->setSourceProperty('title', $headline->textContent);
        // And remove it from the body content.
        $headline->parentNode->removeChild($headline);
      }

      $html = Html::serialize($doc);
      $body[0]['value'] = $html;

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    // Create combined array of taxonomy terms to map to tags.
    // ALL articles will receive a default news-ParkTransit tag
    // which has a TID of 46.
    $tags = [46];

    $reference_fields = [
      'field_mode_of_transportation',
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

    return $map[$tid] ?? FALSE;
  }

}
