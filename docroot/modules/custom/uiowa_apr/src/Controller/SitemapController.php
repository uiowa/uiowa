<?php

namespace Drupal\uiowa_apr\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\uiowa_apr\Apr;
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
   * @var Apr
   */
  protected $apr;

  /**
   * The APR settings immutable config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var ClientInterface $httpClient
   */
  protected $httpClient;

  /**
   * The uiowa_apr logger channel.
   *
   * @var
   */
  protected $logger;

  /**
   * The controller constructor.
   *
   * @param Apr $apr
   *   The uiowa_apr.apr service.
   * @param ConfigFactoryInterface $config
   *   The config factory service.
   * @param ClientInterface $httpClient
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
   */
  public function build(Request $request): Response {
    $params = UrlHelper::buildQuery([
      'key' => $this->config->get('api_key'),
      'path' => $this->config->get('directory.path'),
    ]);

    try {
      $response = $this->httpClient->request('GET', "{$this->apr->endpoint}/people/sitemap?{$params}", [
        'headers' => [
          'Accept' => 'text/plain',
          'Referer' => $request->getSchemeAndHttpHost(),
        ]
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
