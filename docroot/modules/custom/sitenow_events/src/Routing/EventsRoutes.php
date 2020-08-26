<?php

namespace Drupal\sitenow_events\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines a dynamic path based off of the redirect uri variable.
 */
class EventsRoutes implements ContainerInjectionInterface {

  /**
   * The Config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('sitenow_events.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {
    $routes = [];

    $path = $this->config->get('single_event_path') ?: 'event';

    $routes['sitenow_events.single_controller.' . $path] = new Route(
      $path . '/{event_id}/{event_instance}',
      [
        '_controller' => '\Drupal\sitenow_events\Controller\EventsController::build',
      ],
      [
        '_permission'  => 'access content',
        'event_id' => '\d+',
        'event_instance' => '\d+',
      ]
    );

    return $routes;
  }

}
