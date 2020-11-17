<?php

namespace Drupal\uiowa_entities;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a college or academic entity type.
 */
interface UnitInterface extends ConfigEntityInterface {

  const TYPE_COMPONENT = 'component';

}
