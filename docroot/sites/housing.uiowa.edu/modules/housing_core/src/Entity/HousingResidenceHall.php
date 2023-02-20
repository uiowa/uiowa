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
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => '',
      'media_size' => 'media--large',
      'styles' => '',
    ];

    return $default_classes;
  }

}
