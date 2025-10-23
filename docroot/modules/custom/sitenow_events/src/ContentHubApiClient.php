<?php

namespace Drupal\sitenow_events;

use Drupal\uiowa_core\ApiClientBase;

/**
 * The Content Hub API service.
 */
class ContentHubApiClient extends ApiClientBase implements ContentHubApiClientInterface {

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return 'https://content.uiowa.edu/api/v1/';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase() {
    return 'sitenow_events_api_content_hub';
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents(array $options = []): \stdClass|bool {
    return $this->get('views/events_api.json', $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getEventInstances(array $options = []): array|bool {
    return uiowa_core_object_to_array($this->get('views/event_instances_api.json', $options)) ?: FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters($display_id) {
    $options = [
      'query' => [
        'display_id' => $display_id,
      ],
    ];
    return json_decode(json_encode($this->get('views/filters_api.json', $options)), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getPlaces() {
    $options = [
      'query' => [
        'display_id' => 'places',
      ],
    ];
    return json_decode(json_encode($this->get('views/places_api.json', $options)), TRUE);
  }

}
