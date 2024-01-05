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

    // Fill the content with the affected services and buildings,
    // if there are multiple. If there is only one,
    // it will already be displayed in the title.
    $labels = [];
    foreach ([
      'field_alert_service_affected',
      'field_alert_building',
    ] as $field) {
      $field_list = $this->$field;
      if ($field_list->count() > 1) {
        foreach ($field_list->referencedEntities() as $referenced_entity) {
          $labels[] = $referenced_entity->label();
        }
      }
    }
    $build['#content'] = implode(', ', $labels);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $category_id = $this->field_alert_category?->target_id;
    if (in_array($category_id, ['406', '416'])) {
      return [
        'card--layout-left' => 'card--layout-left',
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
