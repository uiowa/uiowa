<?php

namespace Drupal\uiowa_apr\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
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
   * The uiowa_apr config settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The controller constructor.
   *
   * @param \Drupal\uiowa_apr\Apr $apr
   *   The uiowa_apr.apr service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(Apr $apr, ConfigFactoryInterface $config) {
    $this->apr = $apr;
    $this->config = $config->get('uiowa_apr.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_apr.apr'),
      $container->get('config.factory')
    );
  }

  /**
   * Builds the response.
   *
   * @return array
   *   The publications render array.
   */
  public function build() {
    $build = [
      '#attached' => [
        'library' => [
          "uiowa_apr/apr.publications.{$this->apr->environment}",
          'uiowa_apr/styles',
        ],
      ],
      '#type' => 'container',
      '#attributes' => [
        'id' => 'apr-publication-service',
        'role' => 'region',
        'aria-live' => 'polite',
        'aria-label' => 'Publication Listing',
        'class' => [
          'uids-content',
        ],
      ],
    ];

    $build['publications'] = [
      '#type' => 'html_tag',
      '#tag' => 'apr-publications',
      '#attributes' => [
        'api-key' => Html::escape($this->config->get('api_key')),
        'profile-path' => Html::escape($this->config->get('directory.path')) ?? '/apr/people',
        ':page-size' => Html::escape($this->config->get('publications.page_size')) ?? 10,
      ],
    ];

    $departments = Html::escape($this->config->get('publications.departments'));

    if (!empty($departments)) {
      $json = [];

      foreach (explode(PHP_EOL, $departments) as $department) {
        $json[] = (object) [
          'text' => rtrim($department),
          'value' => rtrim($department),
        ];
      }

      $json = json_encode($json);
      $build['publications']['#attributes'][':departments'] = $json;
    }

    return $build;
  }

  /**
   * The page title callback.
   *
   * @return string
   *   The page title.
   */
  public function title() {
    return Html::escape($this->config->get('publications.title')) ?? 'Publications';
  }

}
