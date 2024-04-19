<?php

namespace Drupal\commencement_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for event entries.
 */
class Event extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#meta' => [
        'field_event_venue',
        'field_event_start_date',
        'field_event_end_date',
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--layout-left',
      'media_format' => 'media--circle media--border',
      'media_size' => 'media--small',
    ];
  }

}
