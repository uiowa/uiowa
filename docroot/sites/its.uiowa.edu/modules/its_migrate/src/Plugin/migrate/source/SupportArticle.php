<?php

namespace Drupal\its_migrate\Plugin\migrate\source;

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
 *   id = "its_support_articles",
 *   source_module = "node"
 * )
 */
class SupportArticle extends BaseNodeSource {
  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * Tag-to-name mapping for category.
   *
   * @var array
   */
  protected $tagMapping;

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
    if ($this->migration->id() === 'its_support_article_redirects') {
      return TRUE;
    }

    $body = $row->getSourceProperty('body');

    if (!empty($body)) {
      $this->viewMode = 'medium__no_crop';
      $this->align = 'left';
      // Search for D7 inline embeds and replace with D8+ inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);
      $row->setSourceProperty('body', $body);

      // Extract the summary.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    $tables = [
      'field_data_field_sa_category' => ['field_sa_category_target_id'],
    ];
    $this->fetchAdditionalFields($row, $tables);

    // Set our tagMapping if it's not already.
    if (empty($this->tagMapping)) {
      $this->tagMapping = \Drupal::database()
        ->select('taxonomy_term_field_data', 't')
        ->fields('t', ['name', 'tid'])
        ->condition('t.vid', 'support_article_categories', '=')
        ->execute()
        ->fetchAllKeyed();
    }
    $category = [];

    $tag_name = $row->getSourceProperty('field_sa_category_target_id');

    if (!empty($tag_name)) {
      // Fetch tag names based on TIDs from our old site.
      $tag_results = $this->select('taxonomy_term_data', 't')
        ->fields('t', ['name'])
        ->condition('t.tid', $tag_name, 'IN')
        ->execute()
        ->fetchAll();
      // Take the first value if multiple.
      $tag_name = $tag_results[0]['name'];
      // Check if we have a mapping. If we don't yet,
      // then create a new tag and add it to our map.
      if (!isset($this->tagMapping[$tag_name])) {
        $term = Term::create([
          'name' => $tag_name,
          'vid' => 'support_article_categories',
        ]);
        if ($term->save()) {
          $this->tagMapping[$tag_name] = $term->id();
        }
      }
      // Add the mapped TID to match our tag name.
      $category[] = $this->tagMapping[$tag_name];

    }
    $row->setSourceProperty('category', $category);

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
    if (!$migration->allRowsProcessed() || $migration->id() === 'its_support_articles_redirects') {
      return;
    }
    // Report possible broken links after our known high water mark
    // of articles in which we fixed links.
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }

}
