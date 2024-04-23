<?php

namespace Drupal\its_core\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;
use Drupal\uiowa_core\Entity\RendersAsCardTrait;

/**
 * Provides an interface for paragraph cards on the area of study page entries.
 */
class AlertUpdate extends Paragraph implements RendersAsCardInterface {

  use RendersAsCardTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#subtitle' => 'field_alert_update_date',
      '#content' => 'field_alert_update_description',
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      'card_headline_style' => 'headline--serif',
      'styles' => 'block-margin__bottom',
      'border' => 'borderless',
    ];
  }

}
