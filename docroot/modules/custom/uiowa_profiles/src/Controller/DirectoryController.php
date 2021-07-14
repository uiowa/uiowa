<?php

namespace Drupal\uiowa_profiles\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\uiowa_profiles\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Profiles routes.
 */
class DirectoryController extends ControllerBase {
  use LoggerChannelTrait;

  /**
   * The Profiles service.
   *
   * @var \Drupal\uiowa_profiles\Client
   */
  protected $profiles;

  /**
   * The uiowa_profiles config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The breadcrumb manager service.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbManager
   */
  protected $breadcrumb;

  /**
   * The uiowa_profiles logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * DirectoryController constructor.
   *
   * @param \Drupal\uiowa_profiles\Client $profiles
   *   The Profiles service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \Drupal\Core\Breadcrumb\BreadcrumbManager $breadcrumb
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(Client $profiles, ConfigFactoryInterface $config, BreadcrumbManager $breadcrumb, RouteMatchInterface $routeMatch) {
    $this->profiles = $profiles;
    $this->config = $config->get('uiowa_profiles.settings');
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
      $container->get('config.factory'),
      $container->get('breadcrumb'),
      $container->get('current_route_match')
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
          "uiowa_profiles/client.{$this->profiles->environment}",
          'uiowa_profiles/styles',
        ],
        'drupalSettings' => [
          'uiowaProfiles' => [
            'basePath' => Html::escape($this->config->get('directory.path')),
            'pageSize' => Html::escape($this->config->get('directory.page_size')),
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
          'tabindex' => 0,
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

    $build['uiprof']['client'] = [
      '#type' => 'html_tag',
      '#tag' => 'profiles-client',
      '#attributes' => [
        'api-key' => Html::escape($this->config->get('api_key')),
        'site-name' => $this->config('system.site')->get('name'),
        'directory-name' => Html::escape($this->config->get('directory.title')),
        ':breadcrumbs' => json_encode($breadcrumbs),
        ':host' => 'host',
        ':environment' => 'environment',
        ':page-size' => Html::escape($this->config->get('directory.page_size'))
      ],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'template',
        '#attributes' => [
          'v-slot:intro' => TRUE,
        ],
        'text' => [
          '#type' => 'processed_text',
          '#text' => $this->config->get('directory.intro')['value'],
          '#format' => $this->config->get('directory.intro')['format'],
        ],
      ],
    ];

    if ($slug) {
      $build['uiprof']['client']['#attributes']['slug'] = Html::escape($slug);
    }

    return $build;
  }

}
