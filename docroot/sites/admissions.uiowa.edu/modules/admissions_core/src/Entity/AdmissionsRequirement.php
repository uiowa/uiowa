<?php

namespace Drupal\admissions_core\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;
use Drupal\uiowa_core\Entity\RendersAsCardTrait;

/**
 * Provides an interface for paragraph admissions requirements on area of study page entries.
 */
class AdmissionsRequirement extends Paragraph implements RendersAsCardInterface {

  use RendersAsCardTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'field_ar_intro',
      '#title' => 'requirement_card_label',
      '#meta' => [
        'field_ar_requirement',
        'field_ar_process',
      ],
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
