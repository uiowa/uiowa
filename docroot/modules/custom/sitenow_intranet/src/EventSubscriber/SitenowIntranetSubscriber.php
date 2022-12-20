<?php

namespace Drupal\sitenow_intranet\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sitenow intranet event subscriber.
 */
class SitenowIntranetSubscriber implements EventSubscriberInterface {

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Response event.
   */
  public function onKernelRequest(RequestEvent $event) {
    if (\Drupal::currentUser()->isAnonymous()) {
      $status_code = $event->getRequest()
        ?->attributes
        ?->get('exception')
        ?->getStatusCode();
      if (is_null($status_code) || !($status_code === 401 && $event->getRequestType() === HttpKernelInterface::SUB_REQUEST)) {
        $route_name = $event->getRequest()->attributes->get('_route');
        if (!in_array($route_name, ['user.reset.login', 'samlauth.saml_controller_login'])) {
          throw new UnauthorizedHttpException('Login, yo!');
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
