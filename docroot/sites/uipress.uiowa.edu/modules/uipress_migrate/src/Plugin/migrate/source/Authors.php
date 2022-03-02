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
 *   id = "uipress_authors",
 *   source_module = "node"
 * )
 */
class Authors extends BaseNodeSource {
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
    // Fetch the multi-value roles.
    $tables = [
      'field_data_field_author_roles' => ['field_author_roles_value'],
    ];
    $this->fetchAdditionalFields($row, $tables);
    // If there's a suffix, append it to the last name field.
    if ($suffix = $row->getSourceProperty('field_author_suffix')) {
      $lastname = $row->getSourceProperty('field_author_lastname');
      $lastname[0]['value'] .= ', ' . $suffix[0]['value'];
      $row->setSourceProperty('field_author_lastname', $lastname);
    }
    // Download image and attach it for the person photo.
    if ($image = $row->getSourceProperty('field_image_attach')) {
      // @todo Determine what minimum dimensions to use.
      $this->imageSizeRestrict = [
        'width' => 300,
        'height' => -1,
        'skip' => FALSE,
      ];
      $this->entityId = $row->getSourceProperty('nid');
      $row->setSourceProperty('field_image', $this->processImageField($image[0]['fid'], $image[0]['alt'], $image[0]['title']));
    }
    // Check if we have a facebook, and either append (or replace) with
    // the author website.
    if ($facebook = $row->getSourceProperty('field_author_facebook')) {
      $website = $row->getSourceProperty('field_author_url');
      $website = array_merge($website, $facebook);
      $row->setSourceProperty('field_author_url', $website);
    }
    return TRUE;
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
    // Spit out a report in the logs/cli.
    foreach ($reporter as $entity_id => $filename) {
      $this->logger->notice('Node: @nid, Image: @filename', [
        '@nid' => $entity_id,
        '@filename' => $filename,
      ]);
    }
  }

}
