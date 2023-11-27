<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "articles",
 *  source_module = "node"
 * )
 */
class Articles extends BaseNodeSource {

  use ProcessMediaTrait;
  use LinkReplaceTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    // Search for D7 inline embeds and replace with D8 inline entities.
    $body = $row->getSourceProperty('body');

    if (!empty($body)) {
      $body[0]['value'] = $this->replaceInlineFiles($body[0]['value']);

      foreach ($row->getSource() as $field_name => $value) {
        if (str_starts_with($field_name, 'field_') && !empty($value)) {
          $this->logger->notice($this->t('Unmapped field found in node @nid.', [
            '@nid' => $row->getSourceProperty('nid'),
          ]));
          if (is_array($value)) {
            $value = json_encode($value);
          }
          $body[0]['value'] = $body[0]['value'] . "{$field_name}: {$value}";
        }
      }

      $row->setSourceProperty('body', $body);
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
      $have_run = TRUE;
    }
  }

}
