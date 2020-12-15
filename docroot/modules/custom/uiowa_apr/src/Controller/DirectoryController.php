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
   *
   * @return array
   *   The render array.
   */
  public function build(Request $request) {
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

    // Nonexistent profiles will be redirected back to the directory. Output
    // a message if that is the case to help users understand that.
    $is_not_found = $request->get('not_found');

    if ($is_not_found) {
      $build['error'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'apr-error-message',
        ],
        '#markup' => $this->t('<p>Profile not found. Please use the filters below to find a person.</p>'),
      ];
    }

    $build['intro'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'apr-directory-introduction',
      ],
      '#markup' => check_markup($this->apr->config->get('directory.intro')['value'], $this->apr->config->get('directory.intro')['format']),
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
    ];

    return $build;
  }

  /**
   * Dynamic route title callback.
   */
  public function title() {
    return $this->config('uiowa_apr.settings')->get('directory.title') ?? 'People';
  }

}
