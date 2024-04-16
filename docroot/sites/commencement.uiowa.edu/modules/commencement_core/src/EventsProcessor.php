<?php

namespace Drupal\commencement_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\uiowa_core\EntityProcessorBase;
use Drupal\uiowa_events\ContentHubApiClient;
use Drupal\uiowa_events\ContentHubApiClientInterface;

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
  protected ContentHubApiClientInterface $apiClient;

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
      $this->data = [];
      // Request from Content Hub API to get buildings.
      $response = $this->apiClient->getEvents();
      if (property_exists($response, 'events') && is_array($response->events)) {
        foreach ($response->events as $record) {
          if (property_exists($record, 'event')) {
            $this->data[] = $record->event;
          }
        }
      }
    }
    return $this->data;
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
  protected function processRecord(&$record) {
    if (property_exists($record, 'event_instances')) {
      if (property_exists($record->event_instances[0]->event_instance, 'start') && !is_null($record->event_instances[0]->event_instance->start)) {
        $start_date = $record->event_instances[0]->event_instance->start;
        $record->start = date('Y-m-d\TH:i:s', strtotime($start_date));
      }
      if (property_exists($record->event_instances[0]->event_instance, 'end') && !is_null($record->event_instances[0]->event_instance->end)) {
        $end_date = $record->event_instances[0]->event_instance->end;
        $record->end = date('Y-m-d\TH:i:s', strtotime($end_date));
      }
    }
  }

}
