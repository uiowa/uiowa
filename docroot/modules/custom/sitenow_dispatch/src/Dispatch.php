<?php

namespace Drupal\sitenow_dispatch;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * Dispatch service.
 */
class Dispatch {

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
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a Dispatch object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   *
   * {@inheritdoc}
   *
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $configFactory, $logger) {
    $this->client = $http_client;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }

  /**
   * Helper function for doing get commands from dispatch.
   */
  public function getFromDispatch(string $request, string $API_key = NULL) {

    if (!isset($API_key)) {
      $API_key = $this->configFactory->get('sitenow_dispatch.settings')->get('API_key');
    }

    $response = NULL;

    try {
      $response = $this->client->request('GET', $request, [
        'headers' => [
          'Accept' => 'application/json',
          'x-dispatch-api-key' => $API_key,
        ],
      ]);
    }
    catch (RequestException | GuzzleException | ClientException $e) {
      $this->logger->error($e->getMessage());

      // We've encountered an error, return a blank array.
      return [];
    }

    return json_decode($response->getBody()->getContents());
  }

}
