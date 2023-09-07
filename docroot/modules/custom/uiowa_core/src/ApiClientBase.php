<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * A base class for API client services.
 */
abstract class ApiClientBase implements ApiClientInterface {

  use StringTranslationTrait;

  /**
   * The length of time the cache.
   */
  protected int $cacheLength = 900;

  /**
   * Constructs a DispatchApiClient object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *    The logger.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *    The uiowa_maui cache.
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory object.
   */
  public function __construct(protected ClientInterface $client, protected LoggerInterface $logger, protected CacheBackendInterface $cache, protected ConfigFactoryInterface $configFactory) {}

  /**
   * Returns a string for constructing cache ID's.
   *
   * @return string
   */
  abstract protected function getCacheIdBase();

  /**
   * Get a cache ID for a request.
   *
   * @param string $endpoint
   *   The endpoint.
   * @param array $options
   *   The options.
   *
   * @return string
   *   The cache ID.
   */
  protected function getRequestCacheId(string $endpoint, array $options) {
    // Create a hash for the CID. Can always be decoded for debugging purposes.
    $hash = base64_encode($endpoint . serialize($options));

    return "{$this->getCacheIdBase()}:request:$hash";
  }

  /**
   * {@inheritdoc}
   */
  public function get($endpoint, array $options = []) {
    $cache_id = $this->getRequestCacheId($endpoint, $options);
    if ($cache = $this->cache->get($cache_id)) {
      $data = $cache->data;
    }
    else {
      $data = $this->request('GET', $endpoint, $options);
      if ($data) {
        // Cache for 15 minutes.
        $this->cache->set($cache_id, $data, time() + $this->cacheLength);
      }
    }

    return $data;
  }

}
