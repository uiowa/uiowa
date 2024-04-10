<?php

namespace Drupal\its_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

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
      // Search for D7 inline embeds and replace with D8+ inline entities.
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);
      $row->setSourceProperty('body', $body);
    }

    // Capture support article category by comparing old term name to existing.
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
        ->condition('t.vid', '21', '=')
        ->condition('t.tid', $tag_name, 'IN')
        ->execute()
        ->fetchAll();
      // Take the first value if multiple.
      $tag_name = $tag_results[0]['name'];
      // Check if we have a mapping.
      if (isset($this->tagMapping[$tag_name])) {
        // Add the mapped TID to match our tag name.
        $category[] = $this->tagMapping[$tag_name];
      }

    }
    $row->setSourceProperty('category', $category);

    // Capture field collection items and turn them into paragraphs items.
    $fc_faqs = $row->getSourceProperty('field_sa_faq');
    $faqs_paragraphs = [];
    if (!empty($fc_faqs)) {
      $fc_faqs_content = $this->processFieldCollection($fc_faqs, ['sa_question', 'sa_answer']);
      if (!empty($fc_faqs_content)) {
        foreach ($fc_faqs_content as $faq) {
          if (!empty($faq)) {
            $this->viewMode = 'small__no_crop';
            // Search for D7 inline embeds and replace with D8+ inline entities.
            $faq[0]['field_sa_answer_value'] = $this->replaceInlineFiles($faq[0]['field_sa_answer_value']);
            $paragraph = Paragraph::create([
              'type' => 'support_article_faqs',
              'field_support_faqs_question' => $faq[0]['field_sa_question_value'],
              'field_support_faqs_answer' => [
                'value' => $faq[0]['field_sa_answer_value'],
                'format' => 'filtered_html',
              ],
            ]);

            $paragraph->save();

            $paragraph_item = [
              'target_id' => $paragraph->id(),
              'target_revision_id' => $paragraph->getRevisionId(),
            ];
            $faqs_paragraphs[] = $paragraph_item;
          }
        }
      }

    }
    $row->setSourceProperty('faqs', $faqs_paragraphs);
    return TRUE;
  }

  /**
   * Helper function to snag field collection data.
   *
   * @return array
   *   Array of field collection faqs.
   */
  private function processFieldCollection($items, $collection_fields): array {
    $faqs = [];
    $first_field = array_shift($collection_fields);
    foreach ($items as $item) {
      $query = $this->select("field_data_field_{$first_field}", $first_field)
        ->fields($first_field, ["field_{$first_field}_value"]);
      foreach ($collection_fields as $additional_field) {
        $query->join("field_data_field_{$additional_field}", $additional_field, "{$first_field}.revision_id = {$additional_field}.revision_id");
        $query->fields($additional_field, ["field_{$additional_field}_value"]);
      }
      $results = $query->condition("{$first_field}.revision_id", $item['revision_id'], '=')
        ->execute()
        ->fetchAll();
      $faqs[] = $results;
    }

    return $faqs;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    parent::postImport($event);
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
    $this->postLinkReplace('node', ['node__body' => ['body_value']]);
  }

}
