<?php
namespace Drupal\uiowa_core\EventSubscriber;

use Drupal\views\Ajax\ViewAjaxResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\uiowa_core\Ajax\AfterViewsAjaxCommand;

/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\uiowa_core\EventSubscriber
 */
class ViewsAjaxResponseSubscriber implements EventSubscriberInterface {


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse'];
    $events[KernelEvents::REQUEST][] = ['onRequest'];
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
      $view_id = $view->storage->id();
      $view_display_id = $view->getDisplay()->display['id'];

      // Only act on the view to tweak.
      if (
        $view_id === 'alerts_list_block' &&
        $view_display_id === 'alert_status'
      ) {
        $response->addCommand(new AfterViewsAjaxCommand());
      }
    }
  }
  /**
   * Allows us to alter the Ajax response from a view.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event process.
   */
  public function onRequest(RequestEvent $event) {
    $request = $event->getResponse();

    // Only act on a Views Ajax Response.
    if ($response instanceof ViewAjaxResponse) {
      $view = $response->getView();
      $view_id = $view->storage->id();
      $view_display_id = $view->getDisplay()->display['id'];

      // Only act on the view to tweak.
      if (
        $view_id === 'alerts_list_block' &&
        $view_display_id === 'alert_status'
      ) {
        $response->addCommand(new AfterViewsAjaxCommand());
      }
    }
  }
}
