<?php

namespace Drupal\uiowa_hours;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Hours API service.
 */
class HoursApi {

  const BASE = 'https://hours.iowa.uiowa.edu/api/Hours/fusionrecserv/';

  /**
   * The uiowa_hours logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The uiowa_hours cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $client;

  /**
   * Constructs a Hours object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The uiowa_hours logger channel.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The uiowa_hours cache.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(LoggerInterface $logger, CacheBackendInterface $cache, ClientInterface $http_client) {
    $this->logger = $logger;
    $this->cache = $cache;
    $this->client = $http_client;
  }

  /**
   * Make a Hours API request and return data.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $path
   *   The API path to use. Do not include the base URL.
   * @param array $params
   *   Optional request parameters.
   * @param array $options
   *   Optional request options. All requests expect JSON response data.
   *
   * @return mixed
   *   The API response data.
   */
  public function request($method, $path, array $params = [], array $options = []) {
    // Encode any special characters and trim duplicate slash.
    $path = UrlHelper::encodePath($path);
    $uri = self::BASE . ltrim($path, '/');

    // Append any query string parameters.
    if (!empty($params)) {
      $query = UrlHelper::buildQuery($params);
      $uri .= "?{$query}";
    }

    // Merge additional options with default.
    $options = array_merge($options, [
      'headers' => [
        'Content-type' => 'application/json',
      ],
    ]);

    // Create a hash for the CID. Can always be decoded for debugging purposes.
    $hash = base64_encode($uri . serialize($options));
    $cid = "uiowa_hours:request:{$hash}";
    $data = [];

    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      try {
        $response = $this->client->request($method, $uri, $options);
      }
      catch (RequestException | GuzzleException $e) {
        $this->logger->error('Error encountered getting data from @endpoint: @code @error', [
          '@endpoint' => $uri,
          '@code' => $e->getCode(),
          '@error' => $e->getMessage(),
        ]);
      }

      if (isset($response)) {
        $contents = $response->getBody()->getContents();

        /** @var object $data */
        $data = json_decode($contents);

        // Cache for 15 minutes.
        $this->cache->set($cid, $data, time() + 900);
      }
    }

    return $data;
  }

  /**
   * Get hours based on resource and start date.
   *
   * @return array
   *   The hours object.
   */
  public function getHours($resource_name, $params) {
    $data = $this->request('GET', $resource_name, $params);
    ;
    $date = $params['start'];
    $key = date('Ymd', strtotime($date));
    $markup = $this->t('No hours information available.');
    if ($data->$key) {
      $markup = '';
      $resource_hours = $data->$key;
      // @todo If there are multiple instances then this needs better formatting.
      foreach ($resource_hours as $time) {
        $start = date('g:i a', strtotime($time->startHour));
        $end = '00:00:00' ? strtotime($time->endHour . ', +1 day') : strtotime($time->endHour);
        $end = date('g:i a', $end);
        $markup .= $time->summary . ' ' . $start . ' - ' . $end;
      }
    }
    $result = [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
    return $result;
  }

}
