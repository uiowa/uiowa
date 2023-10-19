<?php

namespace Drupal\uiowa_apr\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\uiowa_apr\Apr;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
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
   */
  protected Apr $apr;

  /**
   * The APR settings immutable config.
   */
  protected ImmutableConfig $config;

  /**
   * The HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * The uiowa_apr logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * The controller constructor.
   *
   * @param \Drupal\uiowa_apr\Apr $apr
   *   The uiowa_apr.apr service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client service.
   */
  public function __construct(Apr $apr, ConfigFactoryInterface $config, ClientInterface $httpClient) {
    $this->apr = $apr;
    $this->config = $config->get('uiowa_apr.settings');
    $this->httpClient = $httpClient;
    $this->logger = $this->getLogger('uiowa_apr');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_apr.apr'),
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
  public function build(Request $request): Response {
    // The returned sitemap URLs already include a slash so remove ours.
    $path = $this->config->get('directory.path');

    $params = UrlHelper::buildQuery([
      'key' => $this->config->get('api_key'),
      'path' => ltrim($path, '/'),
    ]);

    try {
      $response = $this->httpClient->request('GET', "{$this->apr->endpoint}/people/sitemap?{$params}", [
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
