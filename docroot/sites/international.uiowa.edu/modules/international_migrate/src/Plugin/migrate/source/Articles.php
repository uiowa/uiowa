<?php

namespace Drupal\international_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "international_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * Term-to-name mapping for authors.
   *
   * @var array
   */
  protected $termMapping;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
    $query->fields('alias', ['alias']);
    // Make sure our nodes are retrieved in order,
    // and force a highwater mark of our last-most migrated node.
    $query->orderBy('nid');
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
    if ($row->getSourceProperty('nid') < $this->getLastMigrated()) {
      $this->logger->notice('Migrated node: Skipping.');
      return FALSE;
    }
    parent::prepareRow($row);
    $this->logger->notice("Memory usage: @mem",
      ['@mem' => $this->clearMemory(10)]);

    // Only import news newer than January 2015.
    $created_year = date('Y', $row->getSourceProperty('created'));
    if ($created_year < 2015) {
      $this->logger->notice('Older than 2015. Skipping.');
      return FALSE;
    }

    // Get the author tags to build into our mapped
    // field_news_authors value.
    $tables = [
      'field_data_field_news_author' => ['field_news_author_tid'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    $author_tids = $row->getSource('field_news_author_tid');
    if (!empty($author_tids)) {
      $authors = [];
      foreach ($author_tids as $tid) {
        if (!isset($this->termMapping[$tid])) {
          $source_query = $this->select('taxonomy_term_data', 't');
          $source_query = $source_query->fields('t', ['name'])
            ->condition('t.tid', $tid, '=');
          $this->termMapping[$tid] = $source_query->execute()->fetchCol()[0];
        }
        $authors[] = $this->termMapping[$tid];
      }
      $source_org_text = implode(', ', $authors);
      $row->setSourceProperty('field_news_authors', $source_org_text);
    }

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

        if (strpos($href, '/node/') === 0 || stristr($href, 'international.uiowa.edu/node/')) {
          $this->logger->notice('Unable to replace internal link @link in article @article.', [
            '@link' => $href,
            '@article' => $row->getSourceProperty('title'),
          ]);
        }

        $i--;
      }

      $html = Html::serialize($doc);
      $body[0]['value'] = $html;

      // Set the format to filtered_html while we have it.
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }

}
