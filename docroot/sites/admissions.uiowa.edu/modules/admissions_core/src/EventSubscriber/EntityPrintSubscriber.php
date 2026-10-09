<?php

namespace Drupal\admissions_core\EventSubscriber;

use Drupal\entity_print\Event\PrintCssAlterEvent;
use Drupal\entity_print\Event\PrintHtmlAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for entity_print events.
 */
class EntityPrintSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];

    if (class_exists(PrintEvents::class)) {
      $events[PrintEvents::CSS_ALTER][] = 'alterCss';
      $events[PrintEvents::POST_RENDER][] = 'alterHtml';
    }

    return $events;
  }

  /**
   * Attach our CSS library since we don't use a custom theme.
   *
   * @param \Drupal\entity_print\Event\PrintCssAlterEvent $event
   *   The PrintCssAlterEvent event.
   */
  public function alterCss(PrintCssAlterEvent $event) {
    $event->getBuild()['#attached']['library'][] = 'admissions_core/pdf';
  }

  /**
   * Replace Unicode spaces (e.g. U+202F) that render as stray glyphs.
   *
   * @param \Drupal\entity_print\Event\PrintHtmlAlterEvent $event
   *   The PrintHtmlAlterEvent event.
   */
  public function alterHtml(PrintHtmlAlterEvent $event) {
    $html = &$event->getHtml();
    $html = preg_replace('/\p{Zs}/u', ' ', $html) ?? $html;
  }

}
