<?php

namespace Drupal\uiowa_core;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * A base class for API client services.
 */
abstract class ApiClientBase implements ApiClientInterface {

  use StringTranslationTrait;

  /**
   * The length of time the cache.
   */
  protected int $cacheLength = 60;

  /**
   * Constructs a DispatchApiClient object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory object.
   */
  public function __construct(protected ClientInterface $client, protected LoggerInterface $logger, protected CacheBackendInterface $cache, protected ConfigFactoryInterface $configFactory) {}

  /**
   * The last response object that was returned with the API.
   */
  protected ?ResponseInterface $lastResponse = NULL;

  /**
   * {@inheritdoc}
   */
  abstract public function basePath(): string;

  /**
   * Returns a string for constructing cache ID's.
   *
   * @return string
   *   The base cache ID string.
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
  public function lastResponse(): ?ResponseInterface {
    return $this->lastResponse;
  }

  /**
   * {@inheritdoc}
   */
  public function request(string $method, string $endpoint, array $options = []) {
    // Encode any special characters and trim duplicate slash.
    if (!str_starts_with($endpoint, $this->basePath())) {
      $endpoint = UrlHelper::encodePath(ltrim($endpoint, '/'));
      $endpoint = $this->basePath() . $endpoint;
    }

    $this->addAuthToOptions($options);

    // Re-set Accept header in case it was accidentally left out of $options.
    $options['headers']['Accept'] = 'application/json';

    try {
      $this->lastResponse = $this->client->request($method, $endpoint, $options);
    }
    catch (ConnectException $e) {
      $this->logger->error('Unable to connect to the endpoint @endpoint: @code @error', [
        '@endpoint' => $endpoint,
        '@code' => $e->getCode(),
        '@error' => $e->getMessage(),
      ]);

      return FALSE;
    }
    catch (ClientException $e) {
      $this->logger->error('Error encountered getting data from @endpoint: @code @error', [
        '@endpoint' => $endpoint,
        '@code' => $e->getCode(),
        '@error' => $e->getResponse()->getBody()->getContents(),
      ]);

      return FALSE;
    }
    catch (RequestException $e) {
      $this->logger->error('Error encountered getting data from @endpoint: @code @error', [
        '@endpoint' => $endpoint,
        '@code' => $e->getCode(),
        '@error' => $e->getResponse()->getBody()->getContents(),
      ]);

      return FALSE;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Unable to connect to the endpoint @endpoint: @code @error', [
        '@endpoint' => $endpoint,
        '@code' => $e->getCode(),
        '@error' => $e->getMessage(),
      ]);

      return FALSE;
    }

    $data = json_decode($this->lastResponse->getBody()->getContents());

    $this->logger->info('API request sent to: <em>@endpoint</em> and returned code: <em>@code</em>', [
      '@endpoint' => $endpoint,
      '@code' => $this->lastResponse->getStatusCode(),
    ]);

    return $data;
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

  /**
   * {@inheritdoc}
   */
  public function getClient(): ClientInterface {
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function addAuthToOptions(array &$options = []): void {}

}
