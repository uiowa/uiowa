<?php

namespace Drupal\emergency_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\uiowa_core\EntityProcessorBase;
use GuzzleHttp\Client;

/**
 * Sync alert information.
 */
class AlertsProcessor extends EntityProcessorBase {

  /**
   * The http_client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * {@inheritdoc}
   */
  public string $bundle = 'hawk_alert';

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct($this->bundle);
  }

  /**
   * {@inheritdoc}
   */
  protected $fieldSyncKey = 'field_hawk_alert_identifier';

  /**
   * {@inheritdoc}
   */
  protected $apiRecordSyncKey = 'identifier';

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      $emergency_api = \Drupal::service('emergency_core.api');
      $this->data = $emergency_api->getHawkAlerts();
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
    return AlertItemProcessor::process($entity, $record);
  }

  /**
   * {@inheritdoc}
   */
  public function process() {
    if (!$this->getData()) {
      // Log a message that data was not returned.
      static::getLogger('emergency_core')
        ->notice('No data returned for AlertsProcessor::getData().');
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
