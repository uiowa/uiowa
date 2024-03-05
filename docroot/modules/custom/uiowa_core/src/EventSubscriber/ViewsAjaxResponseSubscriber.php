<?php
namespace Drupal\uiowa_core\EventSubscriber;

use Drupal\views\Ajax\ViewAjaxResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\uiowa_core\Ajax\AfterViewsAjaxCommand;

/**
 * Alter a Views Ajax Response.
 */
class ViewsAjaxResponseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse'];
    return $events;
  }

  /**
   * Allows us to alter the Ajax response from a view.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event process.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    // Only act on a Views Ajax Response.
    if ($response instanceof ViewAjaxResponse) {
      $view = $response->getView();

      // Only act on the view to tweak.
      if ($view->storage->id() === 'alert_status') {
        $response->addCommand(new AfterViewsAjaxCommand());
      }
    }
  }
}
