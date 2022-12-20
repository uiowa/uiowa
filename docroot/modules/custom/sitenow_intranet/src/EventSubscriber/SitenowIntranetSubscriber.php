<?php

namespace Drupal\sitenow_intranet\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

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
    // @todo Place code here.
  }

  /**
   * Kernel response event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   Response event.
   */
  public function onKernelResponse(ResponseEvent $event) {

    $entrance = \Drupal::request()->query->get('entrance');
    $originalRequest = \Drupal::request()->getRequestUri();
    if (empty($entrance) && $originalRequest !== '/restrict_ip/access_denied') {
      $url = Url::fromRoute('restrict_ip.access_denied_page', [], [
        'query' => [
          'entrance' => $originalRequest,
        ],
      ]);
      $event->setResponse(new RedirectResponse($url->toString()));
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest'],
      KernelEvents::RESPONSE => ['onKernelResponse'],
    ];
  }

}
