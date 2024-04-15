<?php

namespace Drupal\commencement_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\facilities_core\BuildingItemProcessor;
use Drupal\uiowa_core\EntityProcessorBase;
use Drupal\uiowa_events\ContentHubApiClient;

/**
 * Sync event information.
 */
class EventsProcessor extends EntityProcessorBase {

  /**
   * {@inheritdoc}
   */
  public string $bundle = 'event';

  /**
   * {@inheritdoc}
   */
  protected $fieldSyncKey = 'field_event_id';

  /**
   * {@inheritdoc}
   */
  protected $apiRecordSyncKey = 'id';

  /**
   * The Content Hub API client.
   *
   * @var \Drupal\uiowa_events\ContentHubApiClientInterface
   */
  protected ContentHubApiClient $apiClient;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct();
    $this->apiClient = \Drupal::service('uiowa_events.content_hub_api_client');
  }

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      // Request from Content Hub API to get buildings.
      $this->data = $this->apiClient->getEvents();
    }
    return $this->data['events'];
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    return EventItemProcessor::process($entity, $record['event']);
  }
}
