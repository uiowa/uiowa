<?php

namespace Drupal\sitenow_events\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\TeaserCardInterface;

class Event extends NodeBundleBase implements TeaserCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(&$build) {
    parent::buildCard($build);
    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#subtitle' => 'field_event_when',
      '#meta' => [
        'field_event_virtual',
        'field_event_location',
        'field_event_performer',
      ],
    ]);
  }

}
