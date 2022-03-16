<?php

namespace Drupal\uipress_migrate\Plugin\migrate\source;

use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Row;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Migrate Source plugin.
 *
 * @MigrateSource(
 *   id = "uipress_books",
 *   source_module = "node"
 * )
 */
class Books extends BaseNodeSource {
  use ProcessMediaTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    // Only add the aliases to the query if we're
    // in the redirect migration, otherwise row counts
    // will be off due to one-to-many mapping of nodes to aliases.
    if ($this->migration->id() === 'uipress_book_redirects') {
      $query->leftJoin('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
      $query->fields('alias', ['alias']);
    }
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
    // Fetch the multi-value roles.
    $tables = [
      'field_data_field_uibook_series' => ['field_uibook_series_value'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    $roles = $row->getSourceProperty('field_uibook_series_value');
    $types = [];
    foreach ($roles as $role) {
      $types[] = $this->roleMapping($role);
    }
    $row->setSourceProperty('field_author_roles_value', $types);

    // Download image and attach it for the book cover.
    if ($image = $row->getSourceProperty('field_image_attach')) {
      // Set image size minimums.
      $this->imageSizeRestrict = [
        'width' => 300,
        'height' => -1,
        'skip' => FALSE,
      ];
      $this->entityId = $row->getSourceProperty('nid');
      $row->setSourceProperty('field_image', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }
    return TRUE;
  }

  /**
   * Helper function to map series from the old site to the new site.
   */
  private function seriesMapping($d7_nid) {
    // D7 node ID => D9 term ID
    $mapping = [
      '5746' => '1',
      '3975' => '6',
      '3967' => '11',
      '3977' => '16',
      '3980' => '21',
      '5371' => '26',
      '3979' => '31',
      '3976' => '36',
      '5766' => '41',
      '3969' => '46',
      '3973' => '51',
      '5021' => '56',
      '5751' => '61',
      '3978' => '76',
      '3985' => '86',
      '3968' => '81',
      '3963' => '91',
      '3974' => '96',
      '4068' => '101',
      '3964' => '106',
      '4069' => '116',
      '3970' => '111',
      '3965' => '121',
      '3982' => '126',
      '3971' => '131',
      '3966' => '136',
      '3983' => '141',
      '3972' => '66',
      '3984' => '71',
    ];

    return $mapping[$d7_nid] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function postImport(MigrateImportEvent $event) {
    parent::postImport($event);
    // If nothing to report, then we're done.
    if (empty($this->reporter)) {
      return;
    }
    // Grab our migration map.
    $db = \Drupal::database();
    if (!$db->schema()->tableExists('migrate_map_' . $this->migration->id())) {
      return;
    }
    $mapper = $db->select('migrate_map_' . $this->migration->id(), 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed();
    // Update a reporter for new node ids based on old entity ids.
    $reporter = [];
    foreach ($this->reporter as $entity_id => $filename) {
      $reporter[$mapper[$entity_id]] = $filename;
    }
    // Empty it out so it doesn't keep repeating if the postImport
    // runs multiple times, as it sometimes does.
    $this->reporter = [];
    // Spit out a report in the logs/cli.
    foreach ($reporter as $entity_id => $filename) {
      $this->logger->notice('Node: @nid, Image: @filename', [
        '@nid' => $entity_id,
        '@filename' => $filename,
      ]);
    }
  }

}
