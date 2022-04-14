<?php

namespace Drupal\iowajpec_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "iowajpec_articles",
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
   * Tag-to-name mapping for keywords.
   *
   * @var array
   */
  protected $tagMapping;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Only import news newer than January 2015.
    $query->condition('created', strtotime('2015-01-01'), '>=');
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
    // Skip this node if it comes after our last migrated.
    if ($row->getSourceProperty('nid') < $this->getLastMigrated()) {
      return FALSE;
    }
    parent::prepareRow($row);

    // Get the author tags to build into our mapped
    // field_news_authors value.
    $tables = [
      'field_data_field_news_author' => ['field_news_author_tid'],
      'field_data_field_news_tags' => ['field_news_tags_tid'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    $author_tids = $row->getSourceProperty('field_news_author_tid');
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
      $this->viewMode = 'medium__no_crop';
      $this->align = 'left';
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    // Set our tagMapping if it's not already.
    if (empty($this->tagMapping)) {
      $this->tagMapping = \Drupal::database()
        ->select('taxonomy_term_field_data', 't')
        ->fields('t', ['name', 'tid'])
        ->condition('t.vid', 'tags', '=')
        ->execute()
        ->fetchAllKeyed();
    }

    $tag_tids = $row->getSourceProperty('field_news_tags_tid');
    if (!empty($tag_tids)) {
      // Fetch tag names based on TIDs from our old site.
      $tag_results = $this->select('taxonomy_term_data', 't')
        ->fields('t', ['name'])
        ->condition('t.tid', $tag_tids, 'IN')
        ->execute();
      $tags = [];
      foreach ($tag_results as $result) {
        $tag_name = $result['name'];
        // Check if we have a mapping. If we don't yet,
        // then create a new tag and add it to our map.
        if (!isset($this->tagMapping[$tag_name])) {
          $term = Term::create([
            'name' => $tag_name,
            'vid' => 'tags',
          ]);
          if ($term->save()) {
            $this->tagMapping[$tag_name] = $term->id();
          }
        }

        // Add the mapped TID to match our tag name.
        $tags[] = $this->tagMapping[$tag_name];

      }
      $row->setSourceProperty('tags', $tags);
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
    if (!$migration->allRowsProcessed() || $migration->id() === 'iowajpec_article_redirects') {
      return;
    }
    // Report possible broken links after our known high water mark
    // of articles in which we fixed links.
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }

}
