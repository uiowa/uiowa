<?php

namespace Drupal\uiowa_core;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract entity sync operation.
 */
abstract class EntitySyncAbstract implements EntitySyncInterface {
  /**
   * The entity type.
   *
   * @var string
   */
  public string $entityType = 'node';

  /**
   * The entity bundle.
   *
   * @var string
   */
  public string $bundle;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * The list of existing entity ID's that is being synced.
   *
   * @var array|null
   */
  protected ?array $entityIds;

  /**
   * The number of created entities.
   *
   * @var int
   */
  protected $created = 0;

  /**
   * The number of deleted entities.
   *
   * @var int
   */
  protected $deleted = 0;

  /**
   * The number of updated entities.
   *
   * @var int
   */
  protected $updated = 0;

  /**
   * The entity field/property that is used to match records to entities.
   *
   * @var string
   */
  protected $fieldSyncKey = 'nid';

  /**
   * The key of the API record that is used to match records to entities.
   *
   * @var string
   */
  protected $apiRecordSyncKey = '';

  /**
   * A map of API record sync key values matched to entity ID's.
   *
   * @var array
   */
  protected $keyMap = [];

  /**
   * API records that have been processed.
   *
   * @var array
   */
  protected $processedRecords = [];

  /**
   * An array of entities that have been loaded, keyed by entity ID.
   *
   * @var array
   */
  protected $existingEntities = [];

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct() {
    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * Get the list of entity ID's, querying them if necessary.
   *
   * @return array|null
   *   The list of IDs or NULL.
   */
  public function getEntityIds() {
    if (!isset($this->entityIds)) {
      // Get existing building nodes.
      $this->entityIds = $this->entityTypeManager
        ->getStorage($this->entityType)
        ->getQuery()
        ->condition('type', $this->bundle)
        ->execute();
    }

    return $this->entityIds;
  }

  /**
   * {@inheritdoc}
   */
  public function sync() {
    if (!$this->getData()) {
      // @todo Add a logging message that data was not able to be returned.
      return;
    }

    $storage = $this->entityTypeManager
      ->getStorage($this->entityType);

    // Retrieve building number values from existing nodes.
    if ($this->getEntityIds()) {
      $entities = $storage->loadMultiple($this->getEntityIds());
      foreach ($entities as $entity_id => $entity) {
        if ($entity instanceof FieldableEntityInterface) {
          if ($entity->hasField($this->fieldSyncKey) && !$entity->get($this->fieldSyncKey)->isEmpty()) {
            $this->existingEntities[$entity_id] = $entity;
            $this->keyMap[$entity->get($this->fieldSyncKey)->value] = $entity_id;
          }
        }
      }
    }

    foreach ($this->getData() as $record) {
      $recordSyncKey = $record->{$this->apiRecordSyncKey};
      $this->processedRecords[] = $recordSyncKey;

      $this->processRecord($record);

      $existing_nid = $this->keyMap[$recordSyncKey] ?? NULL;
      $changed = FALSE;

      // Get building number and check to see if existing node exists.
      if (!is_null($existing_nid)) {
        // If existing, update values if different.
        $entity = $this->existingNodes[$existing_nid] ?? $storage->load($existing_nid);
      }
      else {
        // If not, create new.
        $entity = $storage->create([
          'type' => 'building',
        ]);
      }

      if ($entity instanceof ContentEntityInterface) {

        $changed = $this->processEntity($entity, $record);

        if (!is_null($existing_nid)) {
          if ($changed) {
            $entity->setNewRevision();
            $entity->revision_log = 'Updated from source';
            $entity->setRevisionCreationTime(REQUEST_TIME);
            $entity->setRevisionUserId(1);
            $entity->save();
            $this->updated++;
          }
        }
        else {
          $entity->enforceIsNew();
          $entity->save();
          $this->updated++;
        }
      }
    }

    // Loop through to remove nodes that no longer exist in API data.
    if ($this->getEntityIds()) {
      foreach ($this->keyMap as $name => $nid) {
        if (!in_array($name, $this->processedRecords)) {
          $entity = $this->existingNodes[$nid] ?? $storage->load($nid);
          $entity->delete();
          $this->deleted++;
        }
      }
    }
  }

  /**
   * Get the records to be processed.
   */
  protected function getData() {}

  /**
   * If an individual record needs additional processing, handle it here.
   *
   * @param mixed $record
   *   The record being processed.
   */
  protected function processRecord(&$record) {}

  /**
   * Perform processing of the entity.
   *
   * @param $entity
   * @param $record
   *
   * @return bool
   */
  protected function processEntity(&$entity, $record): bool {}

  /**
   * Get created entities count.
   *
   * @return int
   *   The created entity count.
   */
  public function getCreated() {
    return $this->created;
  }

  /**
   * Get deleted entities count.
   *
   * @return int
   *   The deleted entity count.
   */
  public function getDeleted() {
    return $this->deleted;
  }

  /**
   * Get updated entities count.
   *
   * @return int
   *   The updated entity count.
   */
  public function getUpdated() {
    return $this->updated;
  }

}
