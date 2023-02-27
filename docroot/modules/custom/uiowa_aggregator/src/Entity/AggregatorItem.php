<?php

namespace Drupal\uiowa_aggregator\Entity;

use Drupal\aggregator\Entity\Item;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;
use Drupal\uiowa_core\Entity\RendersAsCardTrait;

/**
 * Provides an interface for aggregator items.
 */
class AggregatorItem extends Item implements RendersAsCardInterface {
  use RendersAsCardTrait;

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    $this->buildCardStyles($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#content' => 'description',
      '#media' => 'feed_image',
      '#title' => 'title',
      '#subtitle' => 'post_date',
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      'headline_class' => 'headline--serif',
      'card_media_position' => 'card--layout-left',
      'styles' => 'bg--white',
    ];
  }

}
