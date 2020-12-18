<?php

namespace Drupal\uiowa_apr\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\uiowa_apr\Apr;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for APR routes.
 */
class DirectoryController extends ControllerBase {
  use LoggerChannelTrait;

  /**
   * The APR service.
   *
   * @var \Drupal\uiowa_apr\Apr
   */
  protected $apr;

  /**
   * The uiowa_apr config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig $config
   */
  protected $config;

  /**
   * @var ClientInterface
   */
  protected $client;

  /**
   * The uiowa_apr logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * DirectoryController constructor.
   *
   * @param \Drupal\uiowa_apr\Apr $apr
   *   The APR service.
   * @param ConfigFactoryInterface $config
   *   The config factory service.
   * @param ClientInterface $client
   *   The Guzzle HTTP client.
   */
  public function __construct(Apr $apr, ConfigFactoryInterface $config, ClientInterface $client) {
    $this->apr = $apr;
    $this->config = $config->get('uiowa_apr.settings');
    $this->client = $client;
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
   *   The current request.
   * @param string $slug
   *   The optional person slug. Determines if the directory or profile prints.
   *
   * @return array
   *   The render array.
   */
  public function build(Request $request, $slug = NULL): array {
    $build = [
      '#attached' => [
        'library' => [
          "uiowa_apr/apr.{$this->apr->environment}",
        ],
      ],
      '#type' => 'container',
      '#attributes' => [
        'id' => 'apr-directory-service',
        'role' => 'region',
        'aria-live' => 'polite',
        'aria-label' => 'People Directory',
      ],
    ];

    $build['directory'] = [
      '#type' => 'html_tag',
      '#tag' => 'apr-directory',
      '#attributes' => [
        'api-key' => Html::escape($this->config->get('api_key')),
        'title' => Html::escape($this->config->get('directory.title')),
        'title-selector' => 'h1.page-title',
        ':page-size' => Html::escape($this->config->get('directory.page_size')),
        ':show-title' => 'false',
        ':show-switcher' => Html::escape($this->config->get('directory.show_switcher')),
      ],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'template',
        '#attributes' => [
          'v-slot:introduction' => TRUE,
        ],
        '#markup' => check_markup($this->config->get('directory.intro')['value'], $this->config->get('directory.intro')['format']),
      ],
    ];

    if ($slug) {
      $build['directory']['#attributes']['slug'] = Html::escape($slug);
    }

    return $build;
  }

  /**
   * Dynamic route title callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $slug
   *   The optional route parameter person slug.
   *
   * @return array
   *   The page title render array.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function title(Request $request, $slug = NULL): array {
    $build = [];

    // If a slug is set, this is a profile page. Otherwise, its the directory.
    if ($slug) {
      $params = UrlHelper::buildQuery(['key' => $this->config->get('api_key')]);

      try {
        $response = $this->client->request('GET', "{$this->apr->endpoint}/people/{$slug}/meta?{$params}", [
          'headers' => [
            'Accept' => 'text/plain',
            'Referer' => $request->getSchemeAndHttpHost(),
          ]
        ]);
      }
      catch (RequestException | GuzzleException $e) {
        // If we can't set the page title, throw a 404.
        $this->logger->error($e->getMessage());
        throw new NotFoundHttpException();
      }

      $contents = $response->getBody()->getContents();

      /** @var object $meta */
      $meta = json_decode($contents);

      $build['#markup'] = $this->t('@title', [
        '@title' => $meta->name,
      ]);

      $build['#attached']['html_head_link'][][] = [
        'rel' => 'canonical',
        'href' => Html::escape($this->config->get('directory.canonical')) ?? $request->getHost(),
      ];
    }
    else {
      $build['#markup'] = $this->t('@title', [
        '@title' => $this->config->get('directory.title') ?? 'People',
      ]);
    }

    return $build;
  }

}
