<?php

namespace Drupal\international_core\Entity;

use Drupal\sitenow_people\Entity\Person;

/**
 * Extends Person entity for international.uiowa.edu customizations.
 */
class IghnPerson extends Person {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Check if this person has the ighn_member type.
    $is_ighn_member = FALSE;
    if ($this->hasField('field_person_types') && !$this->get('field_person_types')->isEmpty()) {
      foreach ($this->get('field_person_types')->referencedEntities() as $type) {
        if ($type->id() === 'ighn_member') {
          $is_ighn_member = TRUE;
          break;
        }
      }
    }

    // If this is an IGHN member, add IGHN-specific fields to the meta section.
    if ($is_ighn_member) {
      $ighn_meta_fields = [
        'field_person_ighn_department',
        'field_person_ighn_college',
      ];

      $this->mapFieldsToCardBuild($build, [
        '#meta' => $ighn_meta_fields,
      ]);
    }
  }

}
