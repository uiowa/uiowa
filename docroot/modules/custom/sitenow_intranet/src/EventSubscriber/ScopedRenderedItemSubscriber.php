<?php

namespace Drupal\sitenow_intranet\EventSubscriber;

use Drupal\search_api\Event\GatheringPluginInfoEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\sitenow_intranet\Search\ScopedRenderedItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Points the rendered_item processor at the scoped subclass.
 */
class ScopedRenderedItemSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::GATHERING_PROCESSORS => 'swapRenderedItem',
    ];
  }

  /**
   * Replaces the processor class.
   *
   * @param \Drupal\search_api\Event\GatheringPluginInfoEvent $event
   *   The event holding the processor definitions.
   */
  public function swapRenderedItem(GatheringPluginInfoEvent $event): void {
    $definitions = &$event->getDefinitions();

    if (isset($definitions['rendered_item'])) {
      $definitions['rendered_item']['class'] = ScopedRenderedItem::class;
    }
  }

}
