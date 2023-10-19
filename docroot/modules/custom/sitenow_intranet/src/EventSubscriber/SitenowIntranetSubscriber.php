<?php

namespace Drupal\sitenow_intranet\EventSubscriber;

use Drupal\sitenow_intranet\IntranetHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sitenow intranet event subscriber.
 */
class SitenowIntranetSubscriber implements EventSubscriberInterface {

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a SitenowIntranetSubscriber.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct($current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Response event.
   */
  public function onKernelRequest(RequestEvent $event) {
    $code = IntranetHelper::getStatusCode();
    $status_code_map = IntranetHelper::getStatusCodeMap();
    // The code below prevents us from getting into redirect loops when this
    // event subscriber throws an exception below. Essentially we are checking
    // to see if the request contains one of the codes we have thrown and
    // whether it is a sub-request (which is what happens when you throw the
    // HttpException classes).
    if (is_null($code) || !(in_array($code, array_keys($status_code_map)) && $event->getRequestType() === HttpKernelInterface::SUB_REQUEST)) {
      $route_name = $event->getRequest()
        ?->attributes
        ?->get('_route');
      // Deny anonymous users unless they are hitting routes that need to be
      // accessible.
      if ($this->currentUser->isAnonymous()) {
        if (!in_array($route_name, [
          'robotstxt.content',
          'samlauth.saml_controller_acs',
          'samlauth.saml_controller_login',
          'user.login',
          'user.reset.login',
        ])) {
          throw new UnauthorizedHttpException('Login, yo!');
        }
      }
      // If the user isn't ID # 1 and their only role is authenticated, they are
      // Denied access.
      // Even though the id() method shows that it is supposed to return an int,
      // it sometimes does not, so we are casting the value to an int to ensure
      // it matches.
      elseif ((int) $this->currentUser->id() !== 1 && $this->currentUser->getRoles() === ['authenticated']) {
        if (!in_array($route_name, [
          'entity.user.edit_form',
          'entity.user.canonical',
          'robotstxt.content',
          'user.logout',
        ])) {
          throw new AccessDeniedHttpException('Access denied, yo!');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest'],
    ];
  }

}
