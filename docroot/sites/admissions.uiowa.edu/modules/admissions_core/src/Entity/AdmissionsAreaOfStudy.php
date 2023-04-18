<?php

namespace Drupal\admissions_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for area of study page entries.
 */
class AdmissionsAreaOfStudy extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'body',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--stacked',
      'card_headline_style' => '',
      'media_size' => 'media--large',
      'border' => '',
    ];
  }

}
