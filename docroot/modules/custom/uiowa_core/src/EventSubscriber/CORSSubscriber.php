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

    // Define the allow list for exact paths and patterns.
    // Patterns should end with a /.
    $allowed_paths = [
      '/api/',
    ];

    // Check if the request path is allowed.
    $path_allowed = $this->isPathAllowed($path, $allowed_paths);

    if ($path_allowed) {
      header('Access-Control-Allow-Origin: *');
      header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
      header('Access-Control-Allow-Headers: x-csrf-token, content-type, accept, authorization');
      header('Access-Control-Allow-Credentials: true');
    }
  }

  /**
   * Checks exact paths and path patterns starting with.
   */
  public function isPathAllowed($path, $allowList): bool {
    foreach ($allowList as $allowed) {
      // Check if it's an exact match.
      if ($path === $allowed) {
        return TRUE;
      }
      // Check if it's a pattern (starts with the allowed pattern)
      if (str_ends_with($allowed, '/') && str_starts_with($path, $allowed)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
