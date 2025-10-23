<?php

namespace Drupal\uiowa_events;

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
    return 'uiowa_events_api_content_hub';
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
