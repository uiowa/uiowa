<?php

namespace Drupal\sitenow_people;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a person type entity type.
 */
interface PersonTypeInterface extends ConfigEntityInterface {

  const TYPE_COMPONENT = 'component';

  /**
   * Returns list of person fields to allow based on this type.
   *
   * @return array
   *   The person fields to allow based on this type.
   */
  public function getAllowedFields();

}
