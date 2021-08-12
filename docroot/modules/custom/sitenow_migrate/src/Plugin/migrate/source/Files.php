<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Database;
use Drupal\file\Plugin\migrate\source\d7\File;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Row;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "files",
 *  source_module = "file"
 * )
 */
class Files extends File {

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    parent::prepareRow($row);

    $fileType = explode('/', $row->getSourceProperty('filemime'))[0];

    if ($fileType == 'image') {
      $row->setSourceProperty('meta', $this->fetchMeta($row));
    }

    return TRUE;
  }

  /**
   * If the migrated file is an image, grab the alt and title text values.
   */
  public function fetchMeta($row) {
    $query = $this->select('file_managed', 'f');
    $query->join('field_data_field_file_image_alt_text', 'a', 'a.entity_id = f.fid');
    $query->join('field_data_field_file_image_title_text', 't', 't.entity_id = f.fid');

    $result = $query->fields('a', [
      'field_file_image_alt_text_value',
    ])
      ->fields('t', [
        'field_file_image_title_text_value',
      ])
      ->condition('f.fid', $row->getSourceProperty('fid'))
      ->execute();

    return $result->fetchAssoc();
  }

  /**
   * Pre-rollback event to delete the migration-created media entities.
   *
   * @param \Drupal\migrate\Event\MigrateRollbackEvent $event
   *   The migration event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function preRollback(MigrateRollbackEvent $event) {
    $migration_id = $event->getMigration()->id();
    $migrate_map = 'migrate_map_' . $migration_id;
    // Get our destination file ids.
    $connection = Database::getConnection();
    $query = $connection->select($migrate_map, 'mm')
      ->fields('mm', ['destid1']);
    $fids = $query->execute()->fetchCol();

    // Grab our image media entities that reference files to be removed.
    $query1 = $connection->select('media__field_media_image', 'm_image')
      ->fields('m_image', ['entity_id'])
      ->condition('m_image.field_media_image_target_id', $fids, 'in');
    // Grab our file media entities that reference files to be removed.
    $query2 = $connection->select('media__field_media_file', 'm_file')
      ->fields('m_file', ['entity_id'])
      ->condition('m_file.field_media_file_target_id', $fids, 'in');
    $results = $query1->execute()->fetchCol();
    $results = array_merge($results, $query2->execute()->fetchCol());

    $entityManager = \Drupal::service('entity_type.manager')
      ->getStorage('media');
    $mediaEntities = $entityManager->loadMultiple($results);
    $entityManager->delete($mediaEntities);
  }

}
