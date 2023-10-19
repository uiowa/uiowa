<?php

namespace Drupal\research_migrate\Plugin\migrate\source;

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
  protected $multiValueFields = [
    'field_ovpred_article_tags' => 'tid',
    'field_ovpred_article_contact' => 'target_id',
  ];

  /**
   * Tag-to-name mapping for keywords.
   *
   * @var array
   */
  protected array $tagMapping;

  /**
   * Mapping for article contacts.
   *
   * @var array
   */
  protected array $contactMap;

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
    $tag_tids = $row->getSourceProperty('field_ovpred_article_tags_tid');
    if (!empty($tag_tids)) {
      // Fetch tag names based on TIDs from our old site.
      $tag_results = $this->select('taxonomy_term_data', 't')
        ->fields('t', ['name'])
        ->condition('t.tid', $tag_tids, 'IN')
        ->execute();

      foreach ($tag_results as $result) {
        $tid = $this->createTag($result['name']);
        $tids[] = $tid;
      }
      // Send all final tids to field_tags.
      if (!empty($tids)) {
        $row->setSourceProperty('tags', $tids);
      }
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

    if ($image = $row->getSourceProperty('field_ovpred_article_image')) {
      $row->setSourceProperty('field_image', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }

    // Establish an array to eventually map to field_contact_reference.
    $nids = [];

    // Set our contactMap of person nodes if it's not already.
    if (empty($this->contactMap)) {
      $this->contactMap = \Drupal::database()
        ->select('node_field_data', 'n')
        ->fields('n', ['title', 'nid'])
        ->condition('n.type', 'person', '=')
        ->execute()
        ->fetchAllKeyed();
    }

    // Migrate article contacts from old site
    // and do some re-mapping in the process.
    $contact_nids = $row->getSourceProperty('field_ovpred_article_contact_target_id');
    if (!empty($contact_nids)) {
      // Fetch node data using NIDs from our old site.
      $contact_results = $this->select('node', 'n')
        ->fields('n', ['title', 'nid'])
        ->condition('n.nid', $contact_nids, 'IN')
        ->execute();

      foreach ($contact_results as $result) {
        // Check contactMapping and create node.
        $mapping_result = $this->contactMapping($result['nid']);
        if ($mapping_result != NULL) {
          if ($mapping_result != '0') {
            // There is a mapping result, so switch to use that target_id.
            $nids[] = $mapping_result;
          }
          else {
            // This contact should be skipped and not attached to the content.
            break;
          }
        }
      }
      // Send all final nids to field_contact_reference.
      if (!empty($nids)) {
        $row->setSourceProperty('contacts', $nids);
      }
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
   * Helper function to map contacts.
   */
  private function contactMapping($nid) {
    // D7 contact => D9 contact.
    // If 0, contact will be skipped.
    $mapping = [
      '8201' => '756',
      '4026' => '61',
      '7501' => '241',
      '2291' => '61',
      '2736' => '216',
      '6126' => '141',
      '7796' => '61',
      '6316' => '226',
      '6511' => '141',
      '5921' => '236',
      '5551' => '766',
      '5036' => '416',
      '4821' => '0',
      '1123' => '61',
      '3116' => '361',
      '2566' => '0',
      '1766' => '61',
      '1657' => '771',
      '1658' => '0',
      '1121' => '61',
      '1104' => '291',
      '1101' => '776',
      '1086' => '61',
    ];

    return $mapping[$nid] ?? NULL;
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
