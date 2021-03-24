<?php
namespace Drupal\pharmacy_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "pharmacy_migrate_articles",
 *   source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {
  use ProcessMediaTrait;

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

    // Search for D7 inline embeds and replace with D8 inline entities.
    $content = $row->getSourceProperty('body');

    if (!empty($content)) {
      $content[0]['value'] = preg_replace_callback("|\[\[\{.*?\"fid\":\"(.*?)\".*?\]\]|", [
        $this,
        'entityReplace',
      ], $content[0]['value']);

      $row->setSourceProperty('body', $content);

      // Check summary, and create one if none exists.
      if (empty($content[0]['summary'])) {
        $new_summary = $this->extractSummaryFromText($content[0]['value']);
        $row->setSourceProperty('body_summary', $new_summary);
      }
      else {
        $row->setSourceProperty('body_summary', $content[0]['summary']);
      }
    }

    //@todo: Unlink anchors in body from articles before 2016.
    return TRUE;
  }

}
