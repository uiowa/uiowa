<?php

namespace Drupal\sitenow_dispatch;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * A Dispatch API service.
 *
 * @see: https://apps.its.uiowa.edu/dispatch/api-ref
 */
class Dispatch {
  const BASE = 'https://apps.its.uiowa.edu/dispatch/api/v1/';

  use StringTranslationTrait;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config factory service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The API key for accessing the API.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Constructs a Dispatch object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory object.
   * @param Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $configFactory, LoggerInterface $logger) {
    $this->client = $http_client;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
    $this->apiKey = $this->configFactory->get('sitenow_dispatch.settings')->get('api_key');
  }

  /**
   * Make a Dispatch API request and return JSON-decoded data.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $path
   *   The API path to use. Do not include the base URL.
   * @param array $params
   *   Optional URI query parameters.
   * @param array $options
   *   Additional request options. Accept and API key set automatically.
   *
   * @return mixed
   *   The API response data.
   */
  public function request(string $method, string $endpoint, array $params = [], array $options = []) {
    // Encode any special characters and trim duplicate slash.
    if (!str_starts_with($endpoint, self::BASE)) {
      $endpoint = UrlHelper::encodePath(ltrim($endpoint, '/'));
      $endpoint = self::BASE . $endpoint;
    }

    // Append any query string parameters.
    if (!empty($params)) {
      $query = UrlHelper::buildQuery($params);
      $endpoint .= "?{$query}";
    }

    // Merge additional options with default but allow overriding.
    $options = array_merge([
      'headers' => [
        'x-dispatch-api-key' => $this->apiKey,
      ],
    ], $options);

    // Re-set Accept header in case it was accidentally left out of $options.
    $options['headers']['Accept'] = 'application/json';

    try {
      $response = $this->client->request($method, $endpoint, $options);
    }
    catch (RequestException | GuzzleException | ClientException $e) {
      $this->logger->error('Error encountered getting data from @endpoint: @code @error', [
        '@endpoint' => $endpoint,
        '@code' => $e->getCode(),
        '@error' => $e->getMessage(),
      ]);

      return FALSE;
    }

    return json_decode($response->getBody()->getContents());
  }

  /**
   * Return a list of campaigns keyed by endpoint.
   *
   * @return array
   */
  public function getCampaigns() {
    return $this->getNamesKeyedByEndpoint('campaigns');
  }

  /**
   * Return a list of populations keyed by endpoint.
   *
   * @return array
   */
  public function getPopulations() {
    return $this->getNamesKeyedByEndpoint('populations');
  }

  /**
   * Return a list of suppression lists keyed by endpoint.
   * @return array
   */
  public function getSuppressionLists() {
    return $this->getNamesKeyedByEndpoint('suppressionlists');
  }

  /**
   * Return a list of suppression lists keyed by endpoint.
   * @return array
   */
  public function getTemplates() {
    return $this->getNamesKeyedByEndpoint('templates');
  }

  /**
   * Helper function to generate lists of dispatch options keyed by endpoint.
   *
   * @param $type
   *
   * @return array
   */
  protected function getNamesKeyedByEndpoint($type): array {
    $list = $this->request('GET', $type);
    if (!$list) {
      return [];
    }
    $return = [];
    foreach ($list as $endpoint) {
      $item = $this->request('GET', $endpoint);
      if ($item) {
        $return[$endpoint] = $item->name;
      }
    }
    return $return;
  }

}
