<?php

namespace Drupal\sitenow_dispatch;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * A Dispatch API service.
 *
 * @see: https://apps.its.uiowa.edu/dispatch/api-ref#/
 */
class Dispatch {
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
  }

  /**
   * Helper function for doing get commands from dispatch.
   */
  public function getFromDispatch(string $request, string $api_key = NULL) {

    if (!isset($api_key)) {
      $api_key = $this->configFactory->get('sitenow_dispatch.settings')->get('api_key');
    }

    $response = NULL;

    try {
      $response = $this->client->request('GET', $request, [
        'headers' => [
          'Accept' => 'application/json',
          'x-dispatch-api-key' => $api_key,
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

  /**
   * Helper function for doing post commands to dispatch.
   */
  public function postToDispatch($data, string $endpoint, string $api_key = NULL) {
    if (!isset($api_key)) {
      $api_key = $this->configFactory->get('sitenow_dispatch.settings')->get('api_key');
    }

    try {
      $this->client->post($endpoint, [
        'headers' => [
          'x-dispatch-api-key' => $api_key,
          'accept' => 'application/json',
        ],
        'body' => $data,
      ]);
    }
    catch (RequestException $e) {
      $this->logger->warning($this->t('Dispatch request sent to: <em>@endpoint</em> and failed.', [
        '@endpoint' => $endpoint,
      ]));

      return FALSE;
    }
    return TRUE;
  }

}
