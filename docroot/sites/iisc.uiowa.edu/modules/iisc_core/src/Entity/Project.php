<?php

namespace Drupal\iisc_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for student profile page entries.
 */
class Project extends NodeBundleBase implements RendersAsCardInterface {

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
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'media_size' => 'media--small',
      'media_format' => 'media--widescreen',
      'card_media_position' => 'card--layout-left',
    ];

    return $default_classes;
  }

}
