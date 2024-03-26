<?php

namespace Drupal\its_core\Entity;

use Drupal\sitenow_alerts\Entity\Alert;

/**
 * Provides an interface for its.uiowa.edu alert entries.
 */
class ItsAlert extends Alert {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#meta' => [
        'field_alert_building',
        'field_alert_service_affected',
      ],
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $category_id = $this->field_alert_category?->target_id;
    if (in_array($category_id, ['406', '416'])) {
      return [
        'card_headline_style' => 'headline--serif',
        'media_size' => 'media--small',
        'media_shape' => 'media--circle',
        'borderless' => 'borderless',
      ];
    }
    return [
      ...parent::getDefaultCardStyles(),
    ];
  }

}
