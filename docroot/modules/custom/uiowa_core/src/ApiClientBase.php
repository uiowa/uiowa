<?php

namespace Drupal\uiowa_core;

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
  }

}
