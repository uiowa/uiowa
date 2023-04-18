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
    ]);

    $build['#url'] = $this->get('field_admissions_card_link')?->get(0)?->getUrl()?->toString();
    if (!empty($this->get('field_admissions_card_link')->title)) {
      $build['#link_text'] = $this->get('field_admissions_card_link')->title;
    }
    $build['#link_indicator'] = TRUE;

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $card_id = $this->id();
    $card_media_position = $card_id % 2 == 0 ? 'card--layout-right' : 'card--layout-left';
    return [
      'card_headline_style' => 'headline--serif',
      'card_media_position' => $card_media_position,
      'styles' => 'bg--white',
    ];
  }

}
