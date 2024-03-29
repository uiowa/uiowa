<?php

namespace Drupal\sitenow_people;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a person type entity type.
 */
interface PersonTypeInterface extends ConfigEntityInterface {
  const STATUS_FILTER = 'field_person_type_status_value';
  const TYPE_COMPONENT = 'component';

  /**
   * Returns whether the person type allow alternate labels.
   *
   * @return bool
   *   Whether the person type supports alternate versions.
   */
  public function getAllowFormer();

  /**
   * Returns list of person fields to allow based on this type.
   *
   * @return array
   *   The person fields to allow based on this type.
   */
  public function getAllowedFields();

}
