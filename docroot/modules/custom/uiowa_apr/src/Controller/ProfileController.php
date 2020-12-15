<?php

namespace Drupal\uiowa_apr\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for APR routes.
 */
class ProfileController extends ControllerBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The base API endpoint.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * The controller constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Builds the profile route response.
   *
   * @param string $slug
   *   The slugified profile name.
   */
  public function build($slug) {
    $apr = $this->config('uiowa_apr.settings');
    $env = $apr->get('environment');

    $params = UrlHelper::buildQuery([
      'key' => $this->config('uiowa_apr.settings')->get('api_key'),
      'collapse' => 'false',
      'title' => 'false',
    ]);

    try {
      $response = $this->httpClient->request('GET', "{$this->endpoint}/people/{$slug}?{$params}");
      $data = $response->getBody()->getContents();
    }
    catch (RequestException $e) {
      $this->getLogger('uiowa_apr')->error($e->getMessage());
    }

    if (isset($data)) {
      $build = [
        '#attached' => [
          'library' => [
            "uiowa_apr/apr.{$env}",
          ],
        ],
        '#type' => 'container',
        '#attributes' => [
          'id' => 'apr-directory-service',
          'role' => 'region',
          'aria-live' => 'polite',
          'aria-label' => 'People Directory',
        ],
        'directory' => [
          '#type' => 'html_tag',
          '#tag' => 'apr-directory',
          '#attributes' => [
            'api-key' => $this->apr->config->get('api_key'),
            'slug' => $slug,
            'title-selector' => 'h1.page-title',
            ':show-title' => 'false',
          ],
        ],
        'profile' => [
          '#markup' => $data,
        ],
      ];
    }
    else {
      $build = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'apr-error-message',
        ],
        '#markup' => $this->t('<p>There was an error retrieving profile information. Please try again later.</p>'),
      ];
    }

    return $build;
  }

  /**
   * Dynamic page title route callback.
   */
  public function title(Request $request, $slug) {
    $params = UrlHelper::buildQuery(['key' => $this->config('uiowa_apr.settings')->get('api_key')]);
    $response = $this->httpClient->request('GET', "{$this->endpoint}/people/{$slug}/meta?{$params}");

    if ($contents = $response->getBody()->getContents()) {
      $meta = json_decode($contents);

      $build = [
        '#markup' => $meta->name,
      ];

      $build['#attached']['html_head_link'][][] = [
        'rel' => 'canonical',
        'href' => $this->config('uiowa_apr.settings')->get('directory.canonical') ?? $request->getHost(),
      ];

      return $build;
    }
  }

}
