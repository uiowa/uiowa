<?php

namespace Drupal\now_core\Entity;

use Drupal\sitenow_people\Entity\Person;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for custom Iowa Now person extensions.
 */
class NowPerson extends Person implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    if ($this->view?->id() === 'iowa_now_experts') {
      // Process additional card mappings.
      $this->mapFieldsToCardBuild($build, [
        '#content' => 'field_person_research_areas',
      ]);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    if ($this->view?->id() === 'iowa_now_experts') {
      return parent::getDefaultCardStyles();
    }
    else {
      return parent::getDefaultCardStyles();
    }

  }

}
