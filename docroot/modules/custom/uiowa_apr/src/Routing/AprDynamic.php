<?php

namespace Drupal\uiowa_apr\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Dynamic APR routes.
 */
class AprDynamic implements ContainerInjectionInterface {
  /**
   * The APR config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Dynamic APR routes constructor.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('uiowa_apr.settings');
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Return the dynamic routes based on configuration.
   */
  public function routes() {
    $routes = [];

    $directory_path = $this->config->get('directory.path') ?? 'apr/people';

    $routes['uiowa_apr.directory'] = new Route(
      $directory_path,
      [
        '_controller' => 'Drupal\uiowa_apr\Controller\DirectoryController::build',
        '_title_callback' => 'Drupal\uiowa_apr\Controller\DirectoryController::title',
      ],
      [
        '_permission' => 'access content',
      ]
    );

    $routes['uiowa_apr.profile'] = new Route(
      "{$directory_path}/{slug}",
      [
        '_controller' => 'Drupal\uiowa_apr\Controller\ProfileController::build',
        '_title_callback' => 'Drupal\uiowa_apr\Controller\ProfileController::title',
      ],
      [
        '_permission' => 'access content',
      ]
    );

    return $routes;
  }

}
