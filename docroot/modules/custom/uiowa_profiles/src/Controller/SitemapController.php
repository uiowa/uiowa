<?php

namespace Drupal\uiowa_profiles\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\uiowa_profiles\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for APR routes.
 */
class SitemapController extends ControllerBase {
  use LoggerChannelTrait;

  /**
   * The APR service.
   */
  protected Client $profiles;

  /**
   * The HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * The uiowa_profiles logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * The controller constructor.
   *
   * @param \Drupal\uiowa_profiles\Client $profiles
   *   The uiowa_profiles.profiles service.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client service.
   */
  public function __construct(Client $profiles, ClientInterface $httpClient) {
    $this->profiles = $profiles;
    $this->httpClient = $httpClient;
    $this->logger = $this->getLogger('uiowa_profiles');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_profiles.client'),
      $container->get('http_client')
    );
  }

  /**
   * Builds the response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $key
   *   The directory key.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function build(Request $request, int $key): Response {
    $directory = $this->config('uiowa_profiles.settings')->get('directories')[$key];

    $sitemap = $this->profiles->request('GET', 'people/sitemap', [
      'api-key' => $directory['api_key'],
      'path' => ltrim($directory['path'], '/'),
    ],
    [
      'headers' => [
        'Accept' => 'application/xml',
        'Referer' => $request->getSchemeAndHttpHost(),
      ],
    ]);

    return new Response(
      $sitemap,
      200,
      [
        'Content-Type' => 'application/xml',
      ]
    );
  }

}
