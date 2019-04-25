<?php

namespace Drupal\uiowa_events;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining an event feed entity type.
 */
interface EventsInstanceInterface extends ContentEntityInterface {

  /**
   * Gets the event feed title.
   *
   * @return string
   *   Title of the event feed.
   */
  public function getTitle();

  /**
   * Sets the event feed title.
   *
   * @param string $title
   *   The event feed title.
   *
   * @return \Drupal\uiowa_events\EventsInstanceInterface
   *   The called event feed entity.
   */
  public function setTitle($title);

}
