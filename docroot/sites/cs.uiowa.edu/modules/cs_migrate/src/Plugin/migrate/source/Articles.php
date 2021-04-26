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
          if (strpos($href, '/node/') === 0 || stristr($href, 'cs.uiowa.edu/node/')) {
            $nid = explode('node/', $href)[1];

            if (FALSE) {
              $link->setAttribute('href', $lookup);
              $link->parentNode->replaceChild($link, $link);
              $this->logger->info('Replaced internal link @link in article @article.', [
                '@link' => $href,
                '@article' => $row->getSourceProperty('title'),
              ]);

            }
            else {
              $this->logger->notice('Unable to replace internal link @link in article @article.', [
                '@link' => $href,
                '@article' => $row->getSourceProperty('title'),
              ]);
            }
          }
        }

        $i--;
      }

      $html = Html::serialize($doc);
      $body[0]['value'] = $html;

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

    return TRUE;
  }

}
