<?php

namespace Drupal\uiowa_core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CORS Event Subscriber.
 */
class CORSSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onRequest', 1000];
    return $events;
  }

  /**
   * Tries to handle the options request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    $extension = pathinfo($path, PATHINFO_EXTENSION);

    // Define allowed paths.
    $allowed_paths = [
      '/api/active',
    ];

    // Define allowed extensions.
    $allowed_extensions = [
      'json',
    ];

    // Check if the request path or extension is allowed.
    $path_allowed = in_array($path, $allowed_paths);
    $extension_allowed = in_array($extension, $allowed_extensions);

    if ($path_allowed || $extension_allowed) {
      header('Access-Control-Allow-Origin: *');
      header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
      header('Access-Control-Allow-Headers: x-csrf-token, content-type, accept, authorization');
      header('Access-Control-Allow-Credentials: true');
    }
  }

}
