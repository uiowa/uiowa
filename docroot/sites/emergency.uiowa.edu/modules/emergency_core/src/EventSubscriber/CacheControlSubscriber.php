<?php

namespace Drupal\emergency_core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Implements EventSubscriberInterface to modify cache headers.
 */
class CacheControlSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse', -100],
    ];
  }

  /**
   * Modify cache headers for specific paths.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    $response = $event->getResponse();

    // Check the path and modify headers accordingly.
    if (str_starts_with($request->getPathInfo(), '/api/active')) {
      // Reduce cache, leaving some DoS protection.
      $response->headers->set('Cache-Control', 'max-age=30, must-revalidate, public');
    }
  }

}
