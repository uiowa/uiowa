<?php

namespace Drupal\admissions_core\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\uiowa_core\Entity\RendersAsCard;

/**
 * Provides an interface for student profile page entries.
 */
class AdmissionsRequirement extends Paragraph {

  use RendersAsCard;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'field_ar_intro',
      '#title' => 'field_ar_requirement',
      '#link_text' => 'field_ar_process',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      'headline_class' => 'headline--serif',
    ];

  }

}
