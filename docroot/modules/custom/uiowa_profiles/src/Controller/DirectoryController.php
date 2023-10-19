<?php

namespace Drupal\uiowa_profiles\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\uiowa_profiles\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Profiles routes.
 */
class DirectoryController extends ControllerBase {
  use LoggerChannelTrait;

  /**
   * The Profiles service.
   */
  protected Client $profiles;

  /**
   * The breadcrumb manager service.
   */
  protected BreadcrumbManager $breadcrumb;

  /**
   * The uiowa_profiles logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * The current route match.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * DirectoryController constructor.
   *
   * @param \Drupal\uiowa_profiles\Client $profiles
   *   The Profiles service.
   * @param \Drupal\Core\Breadcrumb\BreadcrumbManager $breadcrumb
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(Client $profiles, BreadcrumbManager $breadcrumb, RouteMatchInterface $routeMatch) {
    $this->profiles = $profiles;
    $this->breadcrumb = $breadcrumb;
    $this->logger = $this->getLogger('uiowa_profiles');
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_profiles.client'),
      $container->get('breadcrumb'),
      $container->get('current_route_match')
    );
  }

  /**
   * Builds the response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param int $key
   *   The directory key.
   * @param string $slug
   *   The optional person slug. Determines if the directory or profile prints.
   *
   * @return array
   *   The render array.
   */
  public function build(Request $request, int $key, string $slug = NULL): array {
    $directory = $this->config('uiowa_profiles.settings')->get('directories')[$key];

    $build = [
      '#attached' => [
        'library' => [
          "uiowa_profiles/client.{$this->profiles->environment}",
          'uiowa_profiles/styles',
        ],
        'drupalSettings' => [
          'uiowaProfiles' => [
            'basePath' => Html::escape($directory['path']),
            'api_key' => Html::escape($directory['api_key']),
            'endpoint' => $this->profiles->endpoint,
            'siteName' => $this->config('system.site')->get('name'),
            'directoryTitle' => Html::escape($directory['title']),
          ],
        ],
      ],
      'uiprof' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'uiprof',
          'role' => 'region',
          'aria-live' => 'polite',
          'aria-labelled-by' => 'profiles-table-label',
          'class' => [
            'uids-content',
          ],
        ],
        'label' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'profiles-table-label',
            'class' => [
              'visually-hidden',
            ],
          ],
          'markup' => [
            '#markup' => $this->t('Profiles people listing in a scrolling container.'),
          ],
        ],
      ],
    ];

    $breadcrumbs = [];

    foreach ($this->breadcrumb->build($this->routeMatch)->getLinks() as $link) {
      $breadcrumbs[] = [
        'label' => $link->getText(),
        'url' => $link->getUrl()->toString(),
      ];
    }

    // The Profiles client will add the directory breadcrumb itself using the
    // directory title. We need to remove that last item then only when the
    // configured directory path is two levels deep, i.e. not off of home (/).
    if (count($breadcrumbs) > 1) {
      array_pop($breadcrumbs);
    }

    $build['uiprof']['client'] = [
      '#type' => 'html_tag',
      '#tag' => 'profiles-client',
      '#attributes' => [
        'api-key' => Html::escape($directory['api_key']),
        'site-name' => $this->config('system.site')->get('name'),
        'directory-name' => Html::escape($directory['title']),
        ':page-size' => Html::escape($directory['page_size']),
        ':breadcrumbs' => json_encode($breadcrumbs),
      ],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'template',
        '#attributes' => [
          'v-slot:intro' => TRUE,
        ],
        'text' => [
          '#type' => 'processed_text',
          '#text' => $directory['intro']['value'],
          '#format' => $directory['intro']['format'],
        ],
      ],
    ];

    if ($slug) {
      $build['uiprof']['client']['#attributes']['slug'] = Html::escape($slug);

      $options = [
        'headers' => [
          'Accept' => 'application/json',
        ],
      ];

      $params = [
        'api-key' => $directory['api_key'],
      ];

      $metadata = $this->profiles->request('GET', "people/$slug/metadata", $params, $options);
      $structured = $this->profiles->request('GET', "people/$slug/structured", $params, $options);

      if (isset($metadata)) {
        $meta = json_decode($metadata);

        $title = [
          '#tag' => 'title',
          '#value' => $this->t('@name | @site_name', [
            '@name' => $meta->name,
            '@site_name' => $this->config('system.site')->get('name'),
          ]),
        ];

        $description = [
          '#tag' => 'meta',
          '#attributes' => [
            'name' => 'description',
            'content' => $this->t('@name - @title - The University of Iowa', [
              '@name' => $meta->name,
              '@title' => $meta->directoryTitle,
            ]),
          ],
        ];
      }

      if (isset($structured)) {
        $schema = [
          '#tag' => 'script',
          '#value' => $structured,
          '#attributes' => [
            'type' => 'application/ld+json',
          ],
        ];

        $build['#attached']['html_head'][] = [
          $schema,
          'schema',
        ];
      }
    }
    else {
      $title = [
        '#tag' => 'title',
        '#value' => $this->t('@title | @site_name', [
          '@title' => $directory['title'],
          '@site_name' => $this->config('system.site')->get('name'),
        ]),
      ];

      $description = [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'description',
          'content' => $this->t('@title - The University of Iowa', [
            '@title' => $directory['title'],
          ]),
        ],
      ];
    }

    if (isset($title)) {
      $build['#attached']['html_head']['title'] = [
        $title,
        'title',
      ];
    }

    if (isset($description)) {
      $build['#attached']['html_head'][] = [
        $description,
        'description',
      ];
    }

    return $build;
  }

}
