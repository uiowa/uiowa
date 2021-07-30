<?php

namespace Drupal\uiowa_profiles\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\uiowa_profiles\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for APR routes.
 */
class SitemapController extends ControllerBase {
  use LoggerChannelTrait;

  /**
   * The APR service.
   *
   * @var \Drupal\uiowa_profiles\Client
   */
  protected $profiles;

  /**
   * The APR settings immutable config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The uiowa_profiles logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The controller constructor.
   *
   * @param \Drupal\uiowa_profiles\Client $profiles
   *   The uiowa_profiles.profiles service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client service.
   */
  public function __construct(Client $profiles, ConfigFactoryInterface $config, ClientInterface $httpClient) {
    $this->profiles = $profiles;
    $this->config = $config->get('uiowa_profiles.settings');
    $this->httpClient = $httpClient;
    $this->logger = $this->getLogger('uiowa_profiles');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_profiles.client'),
      $container->get('config.factory'),
      $container->get('http_client')
    );
  }

  /**
   * Builds the response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function build(Request $request, $key) {
    // The returned sitemap URLs already include a slash so remove ours.
    $directory = $this->config->get('directories')[$key];

    $params = UrlHelper::buildQuery([
      'api-key' => $directory['api_key'],
      'path' => ltrim($directory['path'], '/'),
    ]);

    try {
      $response = $this->httpClient->request('GET', "{$this->profiles->endpoint}/people/sitemap?{$params}", [
        'headers' => [
          'Accept' => 'text/plain',
          'Referer' => $request->getSchemeAndHttpHost(),
        ],
      ]);
    }
    catch (RequestException | GuzzleException $e) {
      // Just throw a 404 here since the Acquia error page is ugly.
      $this->logger->error($e->getMessage());
      throw new NotFoundHttpException();
    }

    $contents = $response->getBody()->getContents();

    return new Response(
      $contents,
      200,
      [
        'Content-Type' => 'text/plain',
      ]
    );
  }

}
