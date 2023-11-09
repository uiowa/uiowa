<?php

namespace Drupal\facilities_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for Facilities artwork entries.
 */
class Artwork extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'field_artwork_status',
      '#subtitle' => 'field_artwork_year',
      '#meta' => ['field_artwork_artist'],
    ]);

    $build['#link_indicator'] = TRUE;

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

