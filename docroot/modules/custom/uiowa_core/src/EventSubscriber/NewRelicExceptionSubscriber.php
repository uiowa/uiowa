<?php

namespace Drupal\uiowa_core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Instructs New Relic APM to ignore HTTP exceptions as transactions.
 *
 * Since New Relic PHP agent v11.5.0.18, HTTP exceptions (404, 403, etc.) are
 * reported as errors. Calling newrelic_ignore_transaction() prevents these
 * from being sampled at all, cleaning up the Error Inbox.
 *
 * @see https://acquia.my.site.com/s/article/Upgrading-New-Relic-PHP-agent-version-for-Acquia-Cloud-Platform
 */
class NewRelicExceptionSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::EXCEPTION][] = ['onException'];
    return $events;
  }

  /**
   * Instructs New Relic to ignore HTTP exception transactions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event): void {
    $throwable = $event->getThrowable();
    if (extension_loaded('newrelic')
      && $throwable instanceof HttpExceptionInterface
      && !$throwable instanceof ServiceUnavailableHttpException
    ) {
      newrelic_ignore_transaction();
    }
  }

}
