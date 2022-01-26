<?php

namespace Drupal\uiowa_auth\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {
  /**
   * The current user.
   *
   * @var Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * Route subscriber constructor.
   *
   * @param Drupal\Core\Sessions\AccountInterface $user
   *   The current user.
   */
  public function __construct(AccountInterface $user) {
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('user.pass')) {
      $route->setRequirement('_access', 'FALSE');
    }

    if ($route = $collection->get('user.login')) {
      $route->setDefaults([
        '_controller' => 'Drupal\uiowa_auth\Controller\LegacyLoginController::build',
      ]);
    }
  }

}
