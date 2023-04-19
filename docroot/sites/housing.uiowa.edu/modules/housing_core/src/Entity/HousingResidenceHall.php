<?php

namespace Drupal\housing_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for residence hall entries.
 */
class HousingResidenceHall extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_headline_style' => 'default',
      'card_media_position' => 'card--stacked',
      'media_size' => 'media--large',
      'styles' => '',
      'border' => '',
    ];
  }

}
