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
class DispatchApiClient implements DispatchApiClientInterface {
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
   * @var string|null
   */
  protected ?string $apiKey = NULL;

  /**
   * Constructs a DispatchApiClient object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory object.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $configFactory, LoggerInterface $logger) {
    $this->client = $http_client;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
    $this->apiKey = $this->configFactory->get('sitenow_dispatch.settings')->get('api_key') ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiKey(): string|null {
    return $this->apiKey;
  }

  /**
   * {@inheritdoc}
   */
  public function setApiKey($key): DispatchApiClientInterface {
    $this->apiKey = $key;
    return $this;
  }

  /**
   * {@inheritdoc}
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
        '@error' => $e->getResponse()->getBody()->getContents(),
      ]);

      return FALSE;
    }

    return json_decode($response->getBody()->getContents());
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

  public function getCommunication($id) {
    return $this->request('GET', $id);
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

  public function getTemplate($id) {
    return $this->request('GET', $id);
  }

  /**
   * Helper function to generate lists of dispatch options keyed by endpoint.
   */
  protected function getNamesKeyedByEndpoint(string $type): array {
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
