<?php

namespace Drupal\uiowa_core\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\uiowa_core\Ajax\WebformDataLayerPushCommand;
use Drupal\uiowa_core\WebformDataLayerHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Delivers queued webform dataLayer events in AJAX responses.
 *
 * @see \Drupal\uiowa_core\WebformDataLayerHelper
 */
class WebformDataLayerSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run before AjaxResponseSubscriber (-100) processes attached assets.
    $events[KernelEvents::RESPONSE][] = ['onResponse', 0];
    return $events;
  }

  /**
   * Adds queued events to AJAX responses that don't redirect.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    $response = $event->getResponse();

    if (!$event->isMainRequest() || !$response instanceof AjaxResponse) {
      return;
    }

    // Leave events queued for the page load that follows a redirect.
    foreach ($response->getCommands() as $command) {
      if (in_array($command['command'] ?? NULL, ['webformRefresh', 'redirect'], TRUE)) {
        return;
      }
    }

    if ($events = WebformDataLayerHelper::pop($event->getRequest())) {
      $response->addCommand(new WebformDataLayerPushCommand($events));
    }
  }

}
