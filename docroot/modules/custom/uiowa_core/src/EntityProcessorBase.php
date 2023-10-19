<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Logger\LoggerChannelTrait;

/**
 * Abstract entity sync operation.
 */
abstract class EntityProcessorBase implements EntityProcessorInterface {
  use LoggerChannelTrait;

  /**
   * The entity type.
   */
  public string $entityType = 'node';

  /**
   * The entity bundle.
   */
  public string $bundle;

  /**
   * The entity type manager.
   */
  public EntityTypeManagerInterface $entityTypeManager;

  /**
   * The list of existing entity ID's that is being synced.
   *
   * @var array|null
   */
  protected ?array $entityIds;

  /**
   * The number of created entities.
   */
  protected int $created = 0;

  /**
   * The number of deleted entities.
   */
  protected int $deleted = 0;

  /**
   * The number of updated entities.
   */
  protected int $updated = 0;

  /**
   * The number of skipped entities.
   */
  protected int $skipped = 0;

  /**
   * The entity field/property that is used to match records to entities.
   */
  protected string $fieldSyncKey = 'nid';

  /**
   * The key of the API record that is used to match records to entities.
   */
  protected string $apiRecordSyncKey = '';

  /**
   * A map of API record sync key values matched to entity ID's.
   *
   * @var array
   */
  protected array $keyMap = [];

  /**
   * API records that have been processed.
   *
   * @var array
   */
  protected array $processedRecords = [];

  /**
   * An array of entities that have been loaded, keyed by entity ID.
   *
   * @var array
   */
  protected array $existingEntities = [];

  /**
   * Constructs an EntityProcessorBase instance.
   */
  public function __construct() {
    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * Get the list of entity ID's, querying them if necessary.
   *
   * @return array|null
   *   The list of IDs or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntityIds(): ?array {
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
  public function process() {
    if (!$this->getData()) {
      // Log a message that data was not returned.
      static::getLogger('uiowa_core')->notice('No data returned for EntityProcessorBase::getData().');
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
            $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
            $entity->setRevisionUserId(1);
            $entity->save();
            $this->updated++;
          }
          else {
            $this->skipped++;
          }
        }
        else {
          $entity->enforceIsNew();
          $entity->save();
          $this->created++;
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
  protected function getData(): void {}

  /**
   * If an individual record needs additional processing, handle it here.
   *
   * @param mixed $record
   *   The record being processed.
   */
  protected function processRecord(mixed &$record): void {}

  /**
   * Perform processing of the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param mixed $record
   *   The source record.
   */
  protected function processEntity(ContentEntityInterface &$entity, mixed $record): bool {}

  /**
   * Get created entities count.
   *
   * @return int
   *   The created entity count.
   */
  public function getCreated(): int {
    return $this->created;
  }

  /**
   * Get deleted entities count.
   *
   * @return int
   *   The deleted entity count.
   */
  public function getDeleted(): int {
    return $this->deleted;
  }

  /**
   * Get updated entities count.
   *
   * @return int
   *   The updated entity count.
   */
  public function getUpdated(): int {
    return $this->updated;
  }

  /**
   * Get updated entities count.
   *
   * @return int
   *   The updated entity count.
   */
  public function getSkipped(): int {
    return $this->skipped;
  }

}
