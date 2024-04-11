<?php

namespace Drupal\emergency_core;

use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Emergency API service.
 */
class EmergencyAPI {
  // Stage endpoint with test data.
  const BASE_URL_1 = 'https://content.getrave.com/cap/uiowatest/channel1';
  // Prod endpoint with real data.
  const BASE_URL_2 = 'https://content.getrave.com/cap/uiowa/channel1';

  /**
   * The emergency_core logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The emergency_core cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Constructs an alert object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The emergency_core logger channel.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The emergency_core cache.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(LoggerInterface $logger, CacheBackendInterface $cache, ClientInterface $http_client) {
    $this->logger = $logger;
    $this->cache = $cache;
    $this->client = $http_client;
  }

  /**
   * Make an Emergency API request and return data.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param array $options
   *   Optional request options. All requests expect JSON response data.
   * @param string $base
   *   The base URL to use for the request. Defaults to self::BASE_URL_1.
   *
   * @return mixed
   *   The API response data.
   */
  public function request($method, array $options = [], $base = self::BASE_URL_1) {

    // Merge additional options with default but allow overriding.
    $options = array_merge([
      'headers' => [
        'Accept' => 'application/xml',
      ],
    ], $options);

    // Default $data to FALSE in case of API fetch failure.
    $data = FALSE;

    try {
      $response = $this->client->request($method, $base, $options);
    }
    catch (RequestException | GuzzleException $e) {
      $this->logger->error('Error encountered getting data from @endpoint: @code @error', [
        '@endpoint' => $base,
        '@code' => $e->getCode(),
        '@error' => $e->getMessage(),
      ]);
    }

    if (isset($response)) {
      $contents = $response->getBody()->getContents();
      $alert = simplexml_load_string($contents);
      $json = json_encode($alert);

      /** @var object $data */
      $data = json_decode($json, TRUE);

    }

    return ($data);
  }

  /**
   * Get all Hawk Alerts.
   *
   * @return array
   *   The alerts object
   */
  public function getHawkAlerts() {
    return $this->request('GET');
  }

}
