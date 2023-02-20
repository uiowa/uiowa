<?php

namespace Drupal\admissions_core\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;
use Drupal\uiowa_core\Entity\RendersAsCardTrait;

/**
 * Provides an interface for paragraph cards on the area of study page entries.
 */
class AdmissionsCard extends Paragraph implements RendersAsCardInterface {

  use RendersAsCardTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'field_admissions_card_content',
      '#media' => 'field_admissions_card_media',
      '#title' => 'field_admissions_card_title',
      '#subtitle' => 'field_admissions_card_subtitle',
      '#url' => $this->get('field_admissions_card_link')->uri,
      '#link_text' => $this->get('field_admissions_card_link')->title,
      '#link_indicator' => TRUE,
      '#linked_element' => TRUE,
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
