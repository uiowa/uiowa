<?php

namespace Drupal\uipress_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for book entries.
 */
class Book extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
//      '#subtitle' => 'field_person_position',
      '#meta' => ['field_book_type'],
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--layout-right',
      'media_size' => 'media--medium',
    ];

    return $default_classes;
  }

}
