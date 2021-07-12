<?php

namespace Drupal\uiowa_profiles\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Dynamic Profiles routes.
 */
class ProfilesDynamic implements ContainerInjectionInterface {
  /**
   * The Profiles config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Dynamic Profiles routes constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('uiowa_profiles.settings');
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

    $directory = $this->config->get('directory.path') ?? '/profiles';

    $routes['uiowa_profiles.directory'] = new Route(
      "{$directory}/{slug}",
      [
        '_controller' => 'Drupal\uiowa_profiles\Controller\DirectoryController::build',
        'slug' => NULL,
      ],
      [
        '_permission' => 'access content',
      ]
    );

    $routes['uiowa_profiles.sitemap'] = new Route(
      "{$directory}/sitemap.txt",
      [
        '_controller' => 'Drupal\uiowa_profiles\Controller\SitemapController::build',
      ],
      [
        '_permission' => 'access content',
      ]
    );

    return $routes;
  }

}
