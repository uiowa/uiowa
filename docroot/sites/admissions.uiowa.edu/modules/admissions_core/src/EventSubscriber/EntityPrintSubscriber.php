<?php


namespace Drupal\admissions_core\EventSubscriber;

use Drupal\entity_print\Event\PrintCssAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntityPrintSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    $events = [];

    if (class_exists(PrintEvents::class)) {
      $events[PrintEvents::CSS_ALTER][] = 'alterCss';
    }

    return $events;
  }

  public function alterCss(PrintCssAlterEvent $event) {
    $event->getBuild()['#attached']['library'][] = 'admissions_core/pdf';
  }
}
