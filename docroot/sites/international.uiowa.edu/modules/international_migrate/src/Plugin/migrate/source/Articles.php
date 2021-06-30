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

  protected $oldies = 0;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
    $query->fields('alias', ['alias']);
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
    $last_migrated_query = \Drupal::database()->select('migrate_map_international_articles', 'm')
      ->fields('m', ['sourceid1'])
      ->orderBy('sourceid1', 'DESC');
    $last_migrated = $last_migrated_query->execute()->fetch()->sourceid1;

    $this->logger->notice('Preparing row: @nid. Last migrated: @last',
      ['@nid' => $row->getSourceProperty('nid'),
        '@last' => $last_migrated]);
    if ($row->getSourceProperty('nid') < $last_migrated) {
      $this->logger->notice('Migrated node: Skipping.');
      return FALSE;
    }
    parent::prepareRow($row);
    $this->logger->notice("Memory usage: @mem",
      ['@mem' => $this->clearMemory(10)]);

    // Only import news newer than January 2015.
    $created_year = date('Y', $row->getSourceProperty('created'));
    if ($created_year < 2015) {
      $this->logger->notice('Total skipped: @oldies',
        ['@oldies' => ++$this->oldies]);
      return FALSE;
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

  public function postImport(MigrateImportEvent $event) {
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }

}
