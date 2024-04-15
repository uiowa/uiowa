<?php

namespace Drupal\commencement_core;

use Drupal\uiowa_core\EntityProcessorBase;
use Drupal\uiowa_facilities\BizHubApiClient;

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
   * The BizHub API client.
   *
   * @var \Drupal\uiowa_facilities\BizHubApiClientInterface
   */
  protected BizHubApiClient $apiClient;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct();
    $this->apiClient = \Drupal::service('uiowa_events.content_hub_client');
  }

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    if (!isset($this->data)) {
      // Request from BizHub API to get buildings.
      $this->data = $this->apiClient->getEvents();
    }
    return $this->data;
  }

}
