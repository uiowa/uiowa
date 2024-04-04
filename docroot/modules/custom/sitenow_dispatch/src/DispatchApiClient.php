<?php

namespace Drupal\sitenow_dispatch;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_core\ApiAuthKeyTrait;
use Drupal\uiowa_core\ApiClientBase;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * A Dispatch API service.
 *
 * @see: https://apps.its.uiowa.edu/dispatch/api-ref
 */
class DispatchApiClient extends ApiClientBase implements DispatchApiClientInterface {
  use ApiAuthKeyTrait;

  /**
   * Constructs a DispatchApiClient object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory object.
   */
  public function __construct(protected ClientInterface $client, protected LoggerInterface $logger, protected CacheBackendInterface $cache, protected ConfigFactoryInterface $configFactory) {
    parent::__construct($client, $logger, $cache, $configFactory);
    $this->setKey($this->configFactory->get('sitenow_dispatch.settings')->get('api_key') ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function basePath(): string {
    return 'https://apps.its.uiowa.edu/dispatch/api/v1/';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdBase(): string {
    return 'sitenow_dispatch';
  }

  /**
   * {@inheritdoc}
   */
  protected function loggerChannel(): string {
    return 'sitenow_dispatch';
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaigns() {
    return $this->getNamesKeyedByEndpoint('campaigns');
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunications($campaign) {
    return $this->getNamesKeyedByEndpoint($campaign . '/communications');
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunication($id) {
    return $this->get($id);
  }

  /**
   * Send a request to add a scheduled message to a communication.
   *
   * @param string $communication_id
   *   A Dispatch communication endpoint.
   * @param string $start_time
   *   The formatted start time for when the message should be sent.
   * @param array $overrides
   *   An array of variable to override communication settings.
   *
   * @return false|string
   *   The message response or FALSE.
   */
  public function postCommunicationSchedule(string $communication_id, string $start_time, array $overrides = []) {
    // Construct the scheduled message object.
    $data = (object) [
      'occurrence' => 'ONE_TIME',
      'startTime' => $start_time,
      'businessDaysOnly' => TRUE,
      'includeBatchResponse' => TRUE,
    ];

    if (!empty($overrides)) {
      $data->communicationOverrideVars = $overrides;
    }

    $this->request('POST', $communication_id . '/schedules', [
      'json' => $data,
    ]);

    $location = $this->lastResponse()->getHeader('Location');

    if (!empty($location)) {
      return $location[0];
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPopulations() {
    return $this->getNamesKeyedByEndpoint('populations');
  }

  /**
   * {@inheritdoc}
   */
  public function getSuppressionLists() {
    return $this->getNamesKeyedByEndpoint('suppressionlists');
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplates() {
    return $this->getNamesKeyedByEndpoint('templates');
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplate($id) {
    return $this->get($id);
  }

  /**
   * Helper function to generate lists of dispatch options keyed by endpoint.
   */
  protected function getNamesKeyedByEndpoint(string $type): array {
    $list = $this->get($type);
    if (!$list) {
      return [];
    }
    $return = [];
    foreach ($list as $endpoint) {
      $item = $this->get($endpoint);
      if ($item) {
        $return[$endpoint] = $item->name;
      }
    }
    return $return;
  }

}
