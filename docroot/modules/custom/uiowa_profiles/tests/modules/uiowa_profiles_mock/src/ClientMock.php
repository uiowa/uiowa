<?php

namespace Drupal\uiowa_profiles_mock;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_profiles\Client;
use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface;

class ClientMock extends Client {
  /**
   * The original service.
   */
  protected $innerService;

  /**
   * The constructor.
   */
  public function __construct(Client $client, ConfigFactoryInterface $configFactory, LoggerInterface $logger, HttpClient $httpClient) {
    $this->innerService = $client;
    parent::__construct($configFactory, $logger, $httpClient);
  }

  public function request($method, $path, array $params, array $options) {
    $data = (object) [
      'name' => 'Foo',
    ];

    return json_encode($data);
  }
}
