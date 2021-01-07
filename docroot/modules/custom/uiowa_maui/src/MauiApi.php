<?php

namespace Drupal\uiowa_maui;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Maui API service.
 *
 * @see: https://api.maui.uiowa.edu/maui/pub/webservices/documentation.page
 */
class MauiApi {

  const BASE = 'https://api.maui.uiowa.edu/maui/api/';

  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * The.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  protected $static;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Constructs a Maui object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   * @param \Drupal\Core\Cache\CacheBackendInterface $static
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(LoggerInterface $logger, CacheBackendInterface $cache, CacheBackendInterface $static, ClientInterface $http_client) {
    $this->logger = $logger;
    $this->cache = $cache;
    $this->static = $static;
    $this->client = $http_client;
  }

  /**
   * Make an API request.
   *
   * @param $method
   * @param $path
   * @param array $params
   * @param array $options
   *
   * @return mixed
   *   The API response data.
   */
  public function request($method, $path, $params = [], $options = []) {
    // Encode any special characters and trim duplicate slash.
    $path = UrlHelper::encodePath($path);
    $uri = self::BASE . ltrim($path, '/');

    // Append any query string parameters.
    if (isset($params)) {
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
    $cid = "uiowa_maui:request:{$hash}";

    if ($static_cache = $this->static->get($cid)) {
      return $static_cache->data;
    }
    else {
      $cache = $this->cache->get($cid);

      if (isset($cache, $cache->data, $cache->expire) &&  time() < $cache->expire) {
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

          /** @var object $meta */
          $data = json_decode($contents);

          // Cache for 15 minutes.
          $this->cache->set($cid, $data, time() + 300);
          $this->static->set($cid, $data, time() + 300);
        }
        else {
          $data = [];
        }
      }

      return $data;
    }
  }

}
