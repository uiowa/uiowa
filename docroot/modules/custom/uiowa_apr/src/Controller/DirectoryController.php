<?php

namespace Drupal\uiowa_apr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uiowa_apr\Apr;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for APR routes.
 */
class DirectoryController extends ControllerBase {
  /**
   * The APR service.
   *
   * @var \Drupal\uiowa_apr\Apr
   */
  protected $apr;

  /**
   * DirectoryController constructor.
   *
   * @param \Drupal\uiowa_apr\Apr $apr
   *   The APR service.
   */
  public function __construct(Apr $apr) {
    $this->apr = $apr;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_apr.apr')
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
        'api-key' => $this->apr->config->get('api_key'),
        'title' => $this->apr->config->get('directory.title'),
        'title-selector' => 'h1.page-title',
        ':page-size' => $this->apr->config->get('directory.page_size'),
        ':show-title' => 'false',
        ':show-switcher' => $this->apr->config->get('directory.show_switcher'),
      ],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'template',
        '#attributes' => [
          'v-slot:introduction' => TRUE,
        ],
        '#markup' => check_markup($this->apr->config->get('directory.intro')['value'], $this->apr->config->get('directory.intro')['format']),
      ],
    ];

    if ($slug) {
      $build['directory']['#attributes']['slug'] = $slug;
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
   */
  public function title(Request $request, $slug = NULL) {
    $build = [];

    if ($slug && $meta = $this->apr->getMeta($slug)) {
      $build['#markup'] = $this->t('@title', [
        '@title' => $meta->name,
      ]);

      $build['#attached']['html_head_link'][][] = [
        'rel' => 'canonical',
        'href' => $this->apr->config->get('directory.canonical') ?? $request->getHost(),
      ];
    }
    else {
      $build['#markup'] = $this->t('@title', [
        '@title' => $this->apr->config->get('directory.title') ?? 'People',
      ]);
    }

    return $build;
  }

}
