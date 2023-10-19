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
  protected array $termMapping;

  /**
   * Tag-to-name mapping for keywords.
   *
   * @var array
   */
  protected array $tagMapping;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Skip specific nodes, as they were already manually
    // migrated over.
    $to_skip = [
      13626,
      13166,
      13616,
      13621,
      13526,
      13596,
      13586,
      13576,
      13126,
      13091,
      13496,
      13551,
    ];
    $query = parent::query();
    // Only import news newer than January 2019.
    $query->condition('created', strtotime('2019-01-01'), '>=');
    $query->condition('n.nid', $to_skip, 'NOT IN');
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

    // Process the publication date field into a usable format for
    // the new article's created date.
    $date = $row->getSourceProperty('field_news_date');
    // Because all our dates are set to midnight, we
    // can adjust the string rather than create a new
    // timezone-sensitive object to then turn to a string.
    // And if we don't shift, we'll be off by a day
    // when we transform to a unix timestamp.
    $date = str_replace('00:00:00', '13:00:00', $date[0]['value']);
    $date = strtotime($date);
    $row->setSourceProperty('field_news_date', $date);

    $body = $row->getSourceProperty('field_news_body');

    if (!empty($body)) {
      $this->viewMode = 'medium__no_crop';
      $this->align = 'left';
      // Search for D7 inline embeds and replace with D8 inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);
      $row->setSourceProperty('field_news_body', $body);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    $tables = [
      'field_data_field_tags' => ['field_tags_tid'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    // Set our tagMapping if it's not already.
    if (empty($this->tagMapping)) {
      $this->tagMapping = \Drupal::database()
        ->select('taxonomy_term_field_data', 't')
        ->fields('t', ['name', 'tid'])
        ->condition('t.vid', 'tags', '=')
        ->execute()
        ->fetchAllKeyed();
    }

    $tag_tids = $row->getSourceProperty('field_tags_tid');
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
    if (!$migration->allRowsProcessed() || $migration->id() === 'iowajpec_articles_redirects') {
      return;
    }
    // Report possible broken links after our known high water mark
    // of articles in which we fixed links.
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }

}
