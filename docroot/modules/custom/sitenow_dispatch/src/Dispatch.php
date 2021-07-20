<?php

namespace Drupal\sitenow_dispatch;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;

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
   * Constructs a Dispatch object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $configFactory) {
    $this->client = $http_client;
    $this->configFactory = $configFactory;
  }

  /**
   * Helper function for doing get commands from dispatch.
   */
  public function getFromDispatch(string $request) {

    $response = NULL;

    try {
      $response = $this->client->request('GET', $request, [
        'headers' => [
          'Accept' => 'application/json',
          'x-dispatch-api-key' => $this->configFactory->get('sitenow_dispatch.settings')->get('API_key'),
        ]
      ]);
    }
    catch (RequestException | GuzzleException $e) {
      $this->logger->error($e->getMessage());
    }
    finally {
      if ($response == NULL) {
        // We've encountered an error, return a blank array.
        return [];
      }
    }

    return json_decode($response->getBody()->getContents());
  }

}
