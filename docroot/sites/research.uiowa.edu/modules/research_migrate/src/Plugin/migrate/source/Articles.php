<?php

namespace Drupal\research_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "research_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;
  use LinkReplaceTrait;

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
    // Skip this node if it comes after our last migrated.
    if ($row->getSourceProperty('nid') < $this->getLastMigrated()) {
      return FALSE;
    }
    parent::prepareRow($row);

    // Skip over the rest of the preprocessing, as it's not needed
    // for redirects. Also avoids duplicating the notices.
    // Return TRUE because the row should be created.
    if ($this->migration->id() === 'research_articles_redirects') {
      return TRUE;
    }

    $body = $row->getSourceProperty('body');

    if (!empty($body)) {
      $this->viewMode = 'medium__no_crop';
      $this->align = 'left';
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);
      $row->setSourceProperty('body', $body);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    // Check for content blocks and log the nodes they are on.
    if ($row->getSourceProperty('field_content_block')) {
      $this->logger->notice('Content blocks found on old /node/@old. Consider revising @article', [
        '@old' => $row->getSourceProperty('nid'),
        '@article' => $row->getSourceProperty('title'),
      ]);
    }

    // Set source link and link directly. Should be one or the other, not both.
    $external_link = $row->getSourceProperty('field_ovpred_article_ext_url');
    $now_link = $row->getSourceProperty('field_ovpred_article_ian_url');
    if (!empty($external_link)) {
      $row->setSourceProperty('custom_source_link', $external_link[0]['url']);
    }
    elseif (!empty($now_link)) {
      $row->setSourceProperty('custom_source_link', $now_link[0]['url']);
    }

    $article_type = $row->getSourceProperty('field_ovpred_article_type')[0]['value'];
    if ($article_type === 'external' || $article_type === 'ianow') {
      $row->setSourceProperty('field_article_source_link_direct', 1);
    }

    if ($image = $row->getSourceProperty('field_news_image')) {
      $row->setSourceProperty('field_image', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    // If we haven't finished our migration, or
    // if we're doing the redirects migration,
    // don't proceed with the following.
    $migration = $event->getMigration();
    if (!$migration->allRowsProcessed() || $migration->id() === 'research_articles_redirects') {
      return;
    }
    // Report possible broken links after our known high water mark
    // of articles in which we fixed links.
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }

}
