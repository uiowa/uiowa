<?php

namespace Drupal\admissions_core\Entity;

use Drupal\sitenow_people\Entity\Person;

/**
 * Provides an interface for admissions.uiowa.edu person entries.
 */
class AdmissionsPerson extends Person {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#meta' => ['field_person_territory'],
    ]);
  }

}
