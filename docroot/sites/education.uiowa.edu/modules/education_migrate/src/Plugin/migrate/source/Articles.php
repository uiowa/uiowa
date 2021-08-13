<?php

namespace Drupal\education_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\taxonomy\Entity\Term;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "education_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * Term-to-term mapping for tags.
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

    // Process the image field.
    $image = $row->getSourceProperty('field_image');
    if (!empty($image)) {
      $mid = $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']);
      $row->setSourceProperty('field_image_mid', $mid);
    }

    $this->getTags($row);

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

      while ($i >= 0) {
        $link = $links->item($i);
        $href = $link->getAttribute('href');

        if (strpos($href, '/node/') === 0 || stristr($href, 'education.uiowa.edu/node/')) {
          $nid = explode('node/', $href)[1];

          if ($lookup = $this->manualLookup($nid)) {
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

        $i--;
      }

      $html = Html::serialize($doc);
      $body[0]['value'] = $html;
      $body[0]['format'] = 'filtered_html';

      $row->setSourceProperty('body', $body);
    }

    // Process the image field.
    $image = $row->getSourceProperty('field_image');

    if (!empty($image)) {
      $mid = $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']);
      $row->setSourceProperty('field_image_mid', $mid);
    }

    $this->clearMemory();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
  }

  /**
   * Map taxonomy to a tag.
   */
  protected function getTags(&$row) {
    // Get the author tags to build into our mapped
    // field_news_authors value.
    $tables = [
      'field_data_field_tags' => ['field_tags_tid'],
      'field_data_field_article_affiliation' => ['field_article_affiliation_target_id'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    foreach (['field_tags', 'field_article_affiliation'] as $field) {
      $tids = $row->getSourceProperty($field);
      if (empty($tids)) {
        continue;
      }
      // Check if we've already found a mapping for each term.
      foreach ($tids as $identifier => $tid) {
        if (isset($this->termMapping[$tid['target_id']])) {
          $new_tids[] = $this->termMapping[$tid['target_id']];
        } else {
          $source_tids[] = $tid['target_id'];
        }
      }
    }
    // If we have unmigrated source terms, create new.
    if (!empty($source_tids)) {
      $source_query = $this->select('taxonomy_term_data', 't');
      $source_query = $source_query->fields('t', [
        'tid',
        'name',
        'description',
      ])
        ->condition('t.tid', $source_tids, 'in');
      $terms = $source_query->distinct()
        ->execute()
        ->fetchAllAssoc('tid');
      foreach ($terms as $tid => $details) {
        $new_term = Term::create([
          'name' => $details['name'],
          'vid' => 'tags',
          'description' => $details['description'],
        ]);
        if ($new_term->save()) {
          $this->termMapping[$tid] = $new_term->id();
          $new_tids[] = $new_term->id();
        }
      }
    }

    // And, if we have any existing or newly created terms,
    // add them back to the field.
    if (!empty($new_tids)) {
      $row->setSourceProperty('article_tids', $new_tids);
    }
  }

}
