<?php

namespace Drupal\emergency_core\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for its.uiowa.edu alert entries.
 */
class HawkAlert extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    $created = $this->get('created')->value;
    $date = \Drupal::service('date.formatter')->format($created, 'short');
    $build['#subtitle'] = $date;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_headline_style' => 'headline--serif',
      'borderless' => 'borderless',
    ];
  }

}
