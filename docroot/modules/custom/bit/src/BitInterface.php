<?php

namespace Drupal\bit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a bit entity type.
 */
interface BitInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the bit title.
   *
   * @return string
   *   Title of the bit.
   */
  public function getTitle();

  /**
   * Sets the bit title.
   *
   * @param string $title
   *   The bit title.
   *
   * @return \Drupal\bit\BitInterface
   *   The called bit entity.
   */
  public function setTitle($title);

  /**
   * Gets the bit creation timestamp.
   *
   * @return int
   *   Creation timestamp of the bit.
   */
  public function getCreatedTime();

  /**
   * Sets the bit creation timestamp.
   *
   * @param int $timestamp
   *   The bit creation timestamp.
   *
   * @return \Drupal\bit\BitInterface
   *   The called bit entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the bit status.
   *
   * @return bool
   *   TRUE if the bit is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the bit status.
   *
   * @param bool $status
   *   TRUE to enable this bit, FALSE to disable.
   *
   * @return \Drupal\bit\BitInterface
   *   The called bit entity.
   */
  public function setStatus($status);

}
