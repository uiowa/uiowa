<?php

namespace Drupal\sitenow_events\Entity;

use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\TeaserCardInterface;

/**
 * Provides an interface for event entries.
 */
class Event extends NodeBundleBase implements TeaserCardInterface {

  /**
   * If entity has link directly to source field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $source_link_direct = 'field_event_series_link_direct';

  /**
   * If entity has source link field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $source_link = 'field_event_series_link';

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
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

    // Handle link directly to source functionality.
    $build['#url'] = $this->getNodeUrl();
  }

}
