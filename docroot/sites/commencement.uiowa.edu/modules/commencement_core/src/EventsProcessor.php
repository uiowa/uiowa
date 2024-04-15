<?php

namespace Drupal\commencement_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\uiowa_core\EntityProcessorBase;
use GuzzleHttp\Client;

/**
 * Sync event information.
 */
class EventsProcessor extends EntityProcessorBase {

  /**
   * The http_client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * {@inheritdoc}
   */
  public string $bundle = 'event';

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct($this->bundle);
  }

  /**
   * {@inheritdoc}
   */
  protected $fieldSyncKey = 'title';

  /**
   * {@inheritdoc}
   */
  protected $apiRecordSyncKey = 'title';

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      $commencement_api = \Drupal::service('commencement_core.api');
      $this->data = $commencement_api->getEvents();
    }
    return $this->data;
  }

  /**
   * Initialize relevant services.
   */
  public function init() {
    $this->client = \Drupal::service('http_client');
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    return EventItemProcessor::process($entity, $record);
  }

  /**
   * {@inheritdoc}
   */
  public function process() {
    if (!$this->getData()) {
      // Log a message that data was not returned.
      static::getLogger('commencement_core')
        ->notice('No data returned for EventsProcessor::getData().');
      return;
    }

    $storage = $this->entityTypeManager
      ->getStorage($this->entityType);

    // Retrieve headline values from existing hawk alerts.
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

    $record = $this->getData();
    $recordSyncKey = $record[$this->apiRecordSyncKey];
    $this->processedRecords[] = $recordSyncKey;

    $info = $record['info'];
    $info['identifier'] = $record['identifier'];
    $record = $info;
    $this->processRecord($record);

    $existing_nid = $this->keyMap[$recordSyncKey] ?? NULL;

    // Get alert identifier and check to see if existing node exists.
    if (!is_null($existing_nid)) {
      // If existing, load node.
      $entity = $this->existingEntities[$existing_nid] ?? $storage->load($existing_nid);
    }
    else {
      // If not, create new.
      $entity = $storage->create([
        'type' => $this->bundle,
      ]);
    }

    if ($entity instanceof ContentEntityInterface) {
      $this->processEntity($entity, $record);

      if (!is_null($existing_nid)) {
        $this->skipped++;
      }
      else {
        $entity->enforceIsNew();
        $entity->setPublished();
        $entity->save();
        $this->created++;
      }
    }
  }

}
