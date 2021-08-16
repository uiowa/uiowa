<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "pages",
 *  source_module = "node"
 * )
 */
class Pages extends BaseNodeSource {

  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Search for D7 inline embeds and replace with D8 inline entities.
    $body = $row->getSourceProperty('body');

    if (isset($body[0])) {
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);
      $row->setSourceProperty('body', $body);

      // Check summary, and create one if none exists.
      $row->setSourceProperty('body_summary', $this->getSummaryFromTextField($body));
    }

    return TRUE;
  }

  /**
   * Functions to run following a completed migration.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migration event.
   */
  public function postImport(MigrateImportEvent $event) {
    static $have_run = FALSE;
    if (!$have_run) {
      $this->reportPossibleLinkBreaks(['node__body' => ['body_value']]);
      $this->postLinkReplace('node', ['node__body' => ['body_value']]);
      $have_run = TRUE;
    }
  }

  /**
   * Override for manual lookup tables of pre-migrated content.
   */
  private function manualLookup(int $nid) {
    static $mapping = [];
    if (empty($mapping)) {
      $database = \Drupal::database();
      // The following works if all destination content is of a NODE type.
      $tables = [
        'migrate_map_d7_article',
        'migrate_map_d7_page',
        'migrate_map_d7_person',
      ];
      foreach ($tables as $table) {
        if ($database->schema()->tableExists($table)) {
          $mapping += $database->select($table, 'mm')
            ->fields('mm', ['sourceid1', 'destid1'])
            ->execute()
            ->fetchAllKeyed();
        }
      }
    }
    return $mapping[$nid] ?? FALSE;
  }

}
