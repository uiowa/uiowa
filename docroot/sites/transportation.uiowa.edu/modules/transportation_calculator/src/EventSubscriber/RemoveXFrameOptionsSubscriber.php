<?php

namespace Drupal\transportation_calculator\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Set X-Frame options.
 */
class RemoveXFrameOptionsSubscriber implements EventSubscriberInterface {

  /**
   * Set header 'Content-Security-Policy' and 'X-Frame options'.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event.
   */
  public function removeFrameOptions(ResponseEvent $event) {
    $response = $event->getResponse();
    $response->headers->set('X-Frame-Options', 'ALLOW-FROM https://parking.uiowa.edu/');
    $response->headers->set('Content-Security-Policy', 'frame-ancestors \'self\' https://parking.uiowa.edu/');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['removeFrameOptions', -10];
    return $events;
  }

}
