<?php

namespace Drupal\tippie_migrate\Plugin\migrate\source;

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
 *   id = "tippie_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * {@inheritdoc}
   */
  protected $multiValueFields = [
    'field_tags' => 'tid',
    'field_news_departments' => 'target_id',
  ];

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
    // Only import news newer than January 2020.
    $query->condition('created', strtotime('2020-01-01'), '>=');
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

    // Skip over the rest of the preprocessing, as it's not needed
    // for redirects. Also avoids duplicating the notices.
    // Return TRUE because the row should be created.
    if ($this->migration->id() === 'tippie_articles_redirects') {
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

    // Establish an array to eventually map to field_tags.
    $tids = [];

    // Set our tagMapping if it's not already.
    if (empty($this->tagMapping)) {
      $this->tagMapping = \Drupal::database()
        ->select('taxonomy_term_field_data', 't')
        ->fields('t', ['name', 'tid'])
        ->condition('t.vid', 'tags', '=')
        ->execute()
        ->fetchAllKeyed();
    }

    // Migrate tags from old site, by getting term name and
    // comparing to existing tags before creating new.
    $tag_names = [];
    $tag_tids = $row->getSourceProperty('field_tags_tid');
    if (!empty($tag_tids)) {
      // Fetch tag names based on TIDs from our old site.
      $tag_results = $this->select('taxonomy_term_data', 't')
        ->fields('t', ['name'])
        ->condition('t.tid', $tag_tids, 'IN')
        ->execute();

      foreach ($tag_results as $result) {
        $tag_name = $result['name'];

        // Update some tags to something else the Tippie team provided.
        $replacements = [
          'DEI' => 'dei',
          'Master of Finance' => 'mfin',
          'faculty award' => 'faculty awards',
          'research' => 'faculty research',
          'Tippie Leadership Collaborative' => 'tlc',
          'analytics' => 'business analytics',
          'BAIS' => 'business analytics',
          'business analytics' => 'business analytics',
          'business analytics dept' => 'business analytics',
          'business analytics master of science' => 'msba',
          'emba' => 'iemba',
          'Executive MBA Program' => 'iemba',
          'finance' => 'finance',
          'Full-time MBA' => 'imba',
          'Iowa MBA' => 'imba',
          'Master of Finance' => 'mfin',
          'Masters in Business Analytics' => 'msba',
          'Masters of Finance' => 'mfin',
          'mba' => 'imba',
          'mba business analytics' => 'msba',
          'MFin' => 'mfin',
          'MIS' => 'msba',
          'MSBA' => 'msba',
          'OMBA' => 'imba',
          'Online MBA' => 'imba',
          'PhD' => 'phd',
          'pmba' => 'imba',
          'Professional MBA Program' => 'imba',
          'Tippie Analytics' => 'business analytics',
          'undergraduate' => 'upo',
          'undergraduate program' => 'upo',
          'Undergraduate Program Office' => 'upo',
          'undergraduates' => 'upo',
        ];
        if (array_key_exists($tag_name, $replacements)) {
          $tag_name = $replacements[$tag_name];
        }

        $tag_names[] = $tag_name;

      }
    }

    // Convert departments to tags with some specific replacements.
    $departments = $row->getSourceProperty('field_news_departments_target_id');
    if (!empty($departments)) {
      // Get department names.
      $department_names = $this->select('node', 'n')
        ->fields('n', ['title'])
        ->condition('n.nid', $departments, 'IN')
        ->execute();

      // List of replacements provided by Tippie. Note: Some have more than one.
      $dept_replacements = [
        'Accounting' => ['accounting', 'mac'],
        'Business Analytics' => ['business analytics', 'msba'],
        'Economics' => ['economics'],
        'Finance' => ['finance', 'mfin'],
        'Graduate Management Programs' => ['gmp'],
        'John Pappajohn Entrepreneurial Center (Iowa JPEC)' => ['iowa jpec'],
        'Management and Entrepreneurship' => ['m and e'],
        'Marketing' => ['marketing'],
        'UI News Services' => ['media mention'],
        'Undergraduate Program' => ['upo'],
      ];
      $department_tags = [];
      foreach ($department_names as $result) {
        $department_name = $result['title'];
        if (array_key_exists($department_name, $dept_replacements)) {
          foreach ($dept_replacements[$department_name] as $replacement) {
            $department_tags[] = $replacement;
          }
        }
        else {
          $department_tags[] = $department_name;
        }
      }

      if (!empty($department_tags)) {
        $department_tags = array_unique($department_tags);
        foreach ($department_tags as $tag_name) {
          $tag_names[] = $tag_name;
        }
      }
    }

    // Map field_news_featured_research to "faculty research" tag.
    $research = $row->getSourceProperty('field_news_featured_research')[0]['value'];
    if ((int) $research === 1) {
      $tag_names[] = 'faculty research';
      // Make sure 'research' is in the mix. Duplicates will be removed later.
      $tag_names[] = 'research';
    }

    // Convert news type to tags, default to 'news' unless media_mention.
    $news_type = $row->getSourceProperty('field_news_type')[0]['value'];
    $tag_names[] = (!empty($news_type) && $news_type === 'media_mention') ? 'media mention' : 'news';

    // Remove any duplicates.
    $tag_names = array_unique($tag_names);

    foreach ($tag_names as $name) {
      $tid = $this->createTag($name);
      $tids[] = $tid;
    }

    // Send all final tids to field_tags.
    if (!empty($tids)) {
      $row->setSourceProperty('tags', $tids);
    }

    // Check for content blocks and log the nodes they are on.
    if ($row->getSourceProperty('field_content_block')) {
      $this->logger->notice('Content blocks found on old /node/@old. Consider revising @article', [
        '@old' => $row->getSourceProperty('nid'),
        '@article' => $row->getSourceProperty('title'),
      ]);
    }

    // Combine news writer and source title together if they both exist.
    // Only the link is mapped to the source link field.
    $custom_org = $row->getSourceProperty('field_news_writer')[0]['value'];
    if ($source = $row->getSourceProperty('field_news_source')) {
      if (!empty($custom_org)) {
        $custom_org = $custom_org . ', ' . $source[0]['title'];
      }
      else {
        $custom_org = $source[0]['title'];
      }
    }
    $row->setSourceProperty('custom_org', $custom_org);

    if ($image = $row->getSourceProperty('field_news_image')) {
      $row->setSourceProperty('field_image', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }
    return TRUE;
  }

  /**
   * Helper function to check for existing tags and create if they don't exist.
   */
  private function createTag($tag_name) {
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

    // Return tid for mapping to field.
    return $this->tagMapping[$tag_name];
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    // If we haven't finished our migration, or
    // if we're doing the redirects migration,
    // don't proceed with the following.
    $migration = $event->getMigration();
    if (!$migration->allRowsProcessed() || $migration->id() === 'tippie_articles_redirects') {
      return;
    }
    // Report possible broken links after our known high water mark
    // of articles in which we fixed links.
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }

}
