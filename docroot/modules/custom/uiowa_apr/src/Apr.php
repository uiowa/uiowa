<?php

namespace Drupal\uiowa_apr;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The APR service sets some dynamic properties based on the environment.
 *
 * @property \Drupal\Core\Config\ImmutableConfig $config The APR config.
 * @property string $environment The APR environment.
 * @property string $endpoint The APR API endpoint.
 */
class Apr {
  /**
   * The APR settings config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The uiowa_apr logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The APR environment.
   *
   * @var string
   */
  protected $environment;

  /**
   * The APR API endpoint.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * Constructs an Apr service object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HttpClient service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The uiowa_apr logger channel.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $httpClient, LoggerInterface $logger) {
    $this->config = $config_factory->get('uiowa_apr.settings');
    $this->httpClient = $httpClient;
    $this->logger = $logger;
    $this->setEnvironment();
    $this->setEndpoint();
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
   * Set the environment property based on config first then AH environment.
   */
  protected function setEnvironment() {
    if ($env = $this->config->get('environment')) {
      $this->environment = $env;
    }
    else {
      $env = getenv('AH_SITE_ENVIRONMENT');

      switch ($env) {
        case 'test':
        case 'prod':
          $this->environment = 'prod';
          break;

        default:
          $this->environment = 'test';
      }
    }
  }

  /**
   * Set the API endpoint based on the environment property.
   */
  protected function setEndpoint() {
    if ($this->environment == 'test') {
      $this->endpoint = 'https://test.its.uiowa.edu/apr';
    }
    elseif ($this->environment == 'prod') {
      $this->endpoint = 'https://apps.its.uiowa.edu/apr';
    }
  }

  /**
   * Get profile metadata from APR API.
   *
   * @param string $slug
   *   The person slug.
   *
   * @return array
   *   The decoded JSON array of metadata information.
   */
  public function getMeta($slug) {
    $params = UrlHelper::buildQuery(['key' => $this->config->get('api_key')]);
    return $this->request('GET', "{$this->endpoint}/people/{$slug}/meta?{$params}", TRUE);
  }

  /**
   * Get profile data from APR API.
   *
   * @param string $slug
   *   The person slug.
   *
   * @return string
   *   The profile HTML string.
   */
  public function getProfile($slug) {
    $params = UrlHelper::buildQuery([
      'key' => $this->config->get('api_key'),
      'collapse' => 'false',
      'title' => 'false',
    ]);

    return $this->request('GET', "{$this->endpoint}/people/{$slug}?{$params}");
  }

  /**
   * Make an APR API request.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $endpoint
   *   The API endpoint to query.
   * @param bool $json
   *   Whether to return decoded JSON or not.
   *
   * @return mixed
   *   The API response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  protected function request($method, $endpoint, $json = FALSE) {
    try {
      $response = $this->httpClient->request($method, $endpoint);
      $contents = $response->getBody()->getContents();

      if ($json) {
        return json_decode($contents);
      }
      else {
        return $contents;
      }
    }
    catch (RequestException | GuzzleException $e) {
      if ($e->getCode() === 404) {
        throw new NotFoundHttpException();
      }
      else {
        $this->logger->error($e->getMessage());
      }
    }
  }

}
