<?php

namespace Drupal\layout_builder_custom;

use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;

/**
 * A helper class for accessing some layout builder information.
 */
class LayoutBuilderHelper {

  use LayoutEntityHelperTrait;

  /**
   * Checks if layout builder is enabled for an entity.
   */
  public function layoutBuilderEnabled(EntityInterface $entity) {
    return $this->isLayoutCompatibleEntity($entity);
  }

}
