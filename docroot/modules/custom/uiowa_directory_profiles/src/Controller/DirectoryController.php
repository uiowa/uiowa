<?php

namespace Drupal\uiowa_directory_profiles\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\uiowa_directory_profiles\DirectoryProfiles;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
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
   * @var \Drupal\uiowa_directory_profiles\DirectoryProfiles
   */
  protected $directory_profiles;

  /**
   * The uiowa_directory_profiles config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The uiowa_directory_profiles logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * DirectoryController constructor.
   *
   * @param \Drupal\uiowa_directory_profiles\DirectoryProfiles $directory_profiles
   *   The APR service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \GuzzleHttp\ClientInterface $client
   *   The Guzzle HTTP client.
   */
  public function __construct(DirectoryProfiles $directory_profiles, ConfigFactoryInterface $config, ClientInterface $client) {
    $this->directory_profiles = $directory_profiles;
    $this->config = $config->get('uiowa_directory_profiles.settings');
    $this->client = $client;
    $this->logger = $this->getLogger('uiowa_directory_profiles');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_directory_profiles.directory_profiles'),
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
  public function build(Request $request, $slug = NULL) {
    $build = [
      '#attached' => [
        'library' => [
          "uiowa_directory_profiles/directory_profiles.directory.{$this->directory_profiles->environment}",
          'uiowa_directory_profiles/styles',
        ],
        'drupalSettings' => [
          'uiowaDirectoryProfiles' => [
            'pageSize' => Html::escape($this->config->get('directory.page_size')),
          ],
        ],
      ],
      '#type' => 'container',
      '#attributes' => [
        'id' => 'uiprof',
        'role' => 'region',
        'aria-live' => 'polite',
        'aria-labelled-by' => 'directory-profiles-table-label',
        'tabindex' => 0,
        'class' => [
          'uids-content',
        ],
      ],
      'label' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'directory-profiles-table-label',
          'class' => [
            'visually-hidden',
          ],
        ],
        'markup' => [
          '#markup' => $this->t('Directory Profiles people listing in a scrolling container.'),
        ],
      ],
    ];

    // Apparently, booleans need to be a string representation of the variable
    // in the APR element attribute values.
    $show_switcher = var_export($this->config->get('directory.show_switcher'), TRUE);

//    <profiles-client
//        api-key="cc484e0a-f93f-4fb5-be6e-f92478b3ce03"
//        site-name="Pediatrics"
//        :host="host"
//        :environment="environment"
//      >
//    </profiles-client>

    $build['directory'] = [
      '#type' => 'html_tag',
      '#tag' => 'profiles-client',
      '#attributes' => [
        'api-key' => Html::escape($this->config->get('api_key')),
        'site-name' => \Drupal::config('system.site')->get('name'),
        ':host' => "host",
        ':environment' => "environment"
      ],
    ];

    $build['vue'] = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#attributes' => [
        'src' => "https://cdn.jsdelivr.net/npm/vue/dist/vue.js",
      ],
    ];

    $build['uiProfiles'] = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      'markup' => [
        '#markup' => "uiProfiles = { basePath: '/' }",
      ],
    ];

    $build['profilesClientJS'] = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#attributes' => [
        'src' => "https://profiles-test.uiowa.edu/api/lib/profiles-client.umd.min.js",
      ],
    ];

    $build['profilesClientJS'] = [
      '#type' => 'html_tag',
      '#tag' => 'link',
      '#attributes' => [
        'href' => 'https://profiles-test.uiowa.edu/api/lib/profiles-client.css',
        'rel' => 'stylesheet'
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
  public function title(Request $request, $slug = NULL) {
    $build = [];

    // If a slug is set, this is a profile page. Otherwise, its the directory.
    if ($slug) {
      $params = UrlHelper::buildQuery(['key' => $this->config->get('api_key')]);

      try {
        $response = $this->client->request('GET', "{$this->directory_profiles->endpoint}/people/{$slug}/meta?{$params}", [
          'headers' => [
            'Accept' => 'text/plain',
            'Referer' => $request->getSchemeAndHttpHost(),
          ],
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
