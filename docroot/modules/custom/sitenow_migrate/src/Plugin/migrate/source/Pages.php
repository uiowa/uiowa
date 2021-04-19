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
  public function postImportProcess(MigrateImportEvent $event) {
    $migration = $event->getMigration();
    $this->postLinkReplace($migration, 'body');
  }

  /**
   * Override for manual lookup tables of pre-migrated content.
   */
  private function manualLookup(int $nid) {
    // Check if the articles migration has run, and is needed for
    // possible mapping.
    $database = \Drupal::database();
    // @todo check the people migration too, once it is functional.
    if ($database->schema()->tableExists('migrate_map_d7_article')) {
      return $database->select('migrate_map_d7_article', 'mm')
        ->fields('mm', ['destid1'])
        ->condition('mm.sourceid1', $nid, '=')
        ->execute()
        ->fetchField();
    }
    return FALSE;
  }

}
