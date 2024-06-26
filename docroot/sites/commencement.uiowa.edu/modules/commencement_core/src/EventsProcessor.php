<?php

namespace Drupal\commencement_core;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\uiowa_core\EntityProcessorBase;
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
   * A reusable timezone object.
   *
   * @var \DateTimeZone|null
   *   The timezone.
   */
  protected $timezone = NULL;

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
    $this->timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      $this->data = [];
      // Request from Content Hub API to get events.
      $response = $this->apiClient->getEvents([
        'query' => [
          'display_id' => 'events',
          'filters' => [
            'enddate' => [
              'value' => [
                'date' => '01-01-2100',
              ],
            ],
            'department' => 7266,
            'type' => 355,
          ],
          'items_per_page' => 100,
        ],
      ]);
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
    // If the location field exists and is not null, it needs to be converted
    // to an entity ID for an existing venue.
    if (property_exists($record, 'location_name') && !is_null($record->location_name)) {
      $record->location_name = $this->findVenueNid($record->location_name);
    }

    // Convert empty strings to null for the room number field.
    // Otherwise the importer 'updates' this field every time it is run.
    if (property_exists($record, 'room_number') && !is_null($record->room_number)) {
      if (($record->room_number) == "") {
        $record->room_number = NULL;
      }
    }

    // If there are event instances embedded.
    if (property_exists($record, 'event_instances') && !empty($record->event_instances)) {
      // Set default duration.
      $record->duration = 0;
      // Check for a start and an end value.
      foreach (['start', 'end'] as $boundary) {
        if (property_exists($record->event_instances[0]->event_instance, $boundary) && !is_null($record->event_instances[0]->event_instance->{$boundary})) {
          $date = DrupalDateTime::createFromFormat(DATE_ATOM, $record->event_instances[0]->event_instance->{$boundary});
          if ($date) {
            $date = $date->setTimezone($this->timezone)->format('U');
            $date -= $date % 60;
            $record->{$boundary} = $date;
          }
        }
      }

      if (property_exists($record, 'start')) {
        if (!property_exists($record, 'end')) {
          $record->end = $record->start;
        }
        else {
          if ($record->start < $record->end) {
            $record->duration = round(($record->end - $record->start) / 60);
          }
          else {
            $record->end = $record->start;
          }
        }
      }
    }
  }

  /**
   * Find a venue node ID.
   *
   * @param string $string
   *   The string being searched.
   *
   * @return int|null
   *   The entity ID of the venue, if it exists.
   */
  protected function findVenueNid($string) {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'venue')
      ->condition('title', $string)
      ->accessCheck()
      ->execute();

    foreach ($nids as $nid) {
      return $nid;
    }

    return NULL;
  }

}
