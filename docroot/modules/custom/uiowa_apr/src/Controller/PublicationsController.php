<?php

namespace Drupal\uiowa_apr\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\uiowa_apr\Apr;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for APR routes.
 */
class PublicationsController extends ControllerBase {

  /**
   * The uiowa_apr.apr service.
   *
   * @var \Drupal\uiowa_apr\Apr
   */
  protected $apr;

  /**
   * The controller constructor.
   *
   * @param \Drupal\uiowa_apr\Apr $apr
   *   The uiowa_apr.apr service.
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
   */
  public function build() {
    $build = [
      '#attached' => [
        'library' => [
          "uiowa_apr/publications.{$this->apr->environment}",
        ],
      ],
      '#type' => 'container',
      '#attributes' => [
        'id' => 'apr-publication-service',
        'role' => 'region',
        'aria-live' => 'polite',
        'aria-label' => 'Publication Listing',
      ],
    ];

    $build['publications'] = [
      '#type' => 'html_tag',
      '#tag' => 'apr-publications',
      '#attributes' => [
        'api-key' => Html::escape($this->apr->config->get('api_key')),
        'profile-path' => Html::escape($this->apr->config->get('directory.path')) ?? '/apr/people',
        ':page-size' => Html::escape($this->apr->config->get('publications.page_size')) ?? 10,
      ],
    ];

    $departments = $this->apr->config->get('publications.departments');

    if (!empty($departments)) {
      $build['publications']['#attributes'][':departments'] = Xss::filter($departments);
    }

    return $build;
  }

  public function title() {
    return Html::escape($this->apr->config->get('publications.title')) ?? 'Publications';
  }

}
