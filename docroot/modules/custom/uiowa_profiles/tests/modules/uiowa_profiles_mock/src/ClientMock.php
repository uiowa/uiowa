<?php

namespace Drupal\uiowa_profiles_mock;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_profiles\Client;
use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface;

/**
 * Mock version of Profiles Client service.
 */
class ClientMock extends Client {
  /**
   * The original service.
   *
   * @var \Drupal\uiowa_profiles\Client
   */
  protected $innerService;

  /**
   * The constructor.
   */
  public function __construct(Client $client, ConfigFactoryInterface $configFactory, LoggerInterface $logger, HttpClient $httpClient) {
    $this->innerService = $client;
    parent::__construct($configFactory, $logger, $httpClient);
  }

  /**
   * {@inheritdoc}
   */
  public function request($method, $path, array $params, array $options) {
    if ($path === 'people/foo-bar/metadata') {
      $data = (object) [
        'name' => 'Foo Bar',
        'directoryTitle' => 'People',
      ];

    }
    elseif ($path === 'people/foo-bar/structured') {
      $data = (object) [
        'email' => 'foo@bar.com',
        'telephone' => '555-555-5555',
        'affiliation' => [
          '@type' => 'Organization',
          '@id' => 'https://uiowa.edu/#CollegeorUniversity',
        ],
      ];
    }

    return json_encode($data);
  }

}
