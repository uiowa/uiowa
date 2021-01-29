<?php


namespace Drupal\admissions_core\EventSubscriber;

use Drupal\entity_print\Event\PrintCssAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntityPrintSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      PrintEvents::CSS_ALTER => 'alterCss',
    ];
  }

  public function alterCss(PrintCssAlterEvent $event) {
    $event->getBuild()['#attached']['library'][] = 'admissions_core/pdf';
  }
}
