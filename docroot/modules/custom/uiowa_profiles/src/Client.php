<?php

namespace Drupal\uiowa_profiles;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The profiles client sets some dynamic properties based on the environment.
 *
 * @property string $environment The Profiles client environment.
 * @property string $endpoint The Profiles client API endpoint.
 */
class Client {
  /**
   * The Profiles settings config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * The uiowa_profiles logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The Profiles environment.
   *
   * @var string
   */
  protected $environment;

  /**
   * The Profiles API endpoint.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * The guzzle client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a Profiles service object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The uiowa_profiles logger channel.
   * @param \GuzzleHttp\Client $httpClient
   *   The http client service.
   *
   * @throws \Exception
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, HttpClient $httpClient) {
    $this->config = $config_factory->get('uiowa_profiles.settings');
    $this->logger = $logger;
    $this->httpClient = $httpClient;
    $this->environment = $this->setEnvironment();
    $this->endpoint = $this->setEndpoint();
  }

  /**
   * Get a protected property.
   *
   * @param string $name
   *   The property name.
   *
   * @return mixed
   *   The property value.
   */
  public function __get($name) {
    return $this->$name;
  }

  /**
   * Set the environment value based on config first then AH environment.
   */
  protected function setEnvironment() {
    if ($env = $this->config->get('environment')) {
      $this->logger->info('Profiles environment configuration set to @env. Using config instead of AH environment.', [
        '@env' => $env,
      ]);

      $environment = $env;
    }
    else {
      $env = getenv('AH_SITE_ENVIRONMENT');

      switch ($env) {
        case 'test':
        case 'prod':
          $environment = 'prod';
          break;

        default:
          $environment = 'test';
          break;
      }
    }

    return $environment;
  }

  /**
   * Set the API endpoint based on the environment property.
   *
   * @throws \Exception
   *
   * @return string
   *   The API endpoint.
   */
  protected function setEndpoint() {
    $endpoint = '';

    if ($this->environment === 'test') {
      $endpoint = 'https://profiles-test.uiowa.edu/api';
    }
    elseif ($this->environment === 'prod') {
      $endpoint = 'https://profiles.uiowa.edu/api';
    }

    if (!isset($endpoint)) {
      $this->logger->error('Invalid environment @env. Unable to set API endpoint.', [
        '@env' => $this->environment,
      ]);
    }

    return $endpoint;
  }

  /**
   * Make an API request.
   *
   * @param string $method
   *   The request type.
   * @param string $path
   *   The URL path to query.
   * @param array $params
   *   The request parameters as an array. The api-key must be set.
   * @param array $options
   *   The request options as an array. The expected Accept header must be set.
   *
   * @return string
   *   The API response data.
   */
  public function request($method, $path, array $params, array $options) {
    $path = ltrim($path, '/');
    $params = UrlHelper::buildQuery($params);

    try {
      $response = $this->httpClient->request($method, "$this->endpoint/$path?$params", $options);
    }
    catch (RequestException | GuzzleException $e) {
      $this->logger->error($e->getMessage());
      $code = $e->getCode();

      if ((int) $code === 404) {
        throw new NotFoundHttpException();
      }
      else {
        throw new HttpException($code, 'An error occurred while retrieving profile information.');
      }
    }

    return $response->getBody()->getContents();
  }

}
