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

    // Replace the meta display with the affected services and buildings,
    // if there are multiple,
    // rather than the core Alert meta of alert categories.
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
    $build['#meta'] = implode(', ', $labels);
    $build['#url'] = $this->getNodeUrl();
    // @todo Update this.
    $build['#link_text'] = 'View more';

    switch ($this->field_alert_category->target_id) {
      // Outage.
      case '406':
        // @todo Update this.
        $build['#media']['#prefix'] = '<div class="alert__icon">';
        $build['#media']['#suffix'] = '</div>';
        $build['#media']['#markup'] = '<span class="fa-stack fa-1x"> <span class="fas fa-circle fa-stack-2x" role="presentation"></span> <span class="fas fa-stack-1x fa-inverse fa-exclamation" role="presentation"></span></span>';
        break;

      // Service Degradation.
      case '416':
        $build['#media']['#prefix'] = '<div class="alert__icon">';
        $build['#media']['#suffix'] = '</div>';
        $build['#media']['#markup'] = '<span class="fa-stack fa-1x"> <span class="fas fa-circle fa-stack-2x" role="presentation"></span> <span class="fas fa-stack-1x fa-inverse fa-triangle-exclamation" role="presentation"></span> </span>';
        break;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    switch ($this->field_alert_category->target_id) {
      // Outage.
      case '406':
        return [
          'styles' => 'alert alert--icon  alert--danger',
          'card--layout-left' => 'card--layout-left',
          'media_size' => 'media--small',
          'media_shape' => 'media--circle',
        ];

      case '416':
        return [
          'styles' => 'alert alert--icon  alert--warning',
          'card--layout-left' => 'card--layout-left',
          'media_size' => 'media--small',
          'media_shape' => 'media--circle',
        ];

      default:
        return [
          ...parent::getDefaultCardStyles(),
          'styles' => '',
        ];
    }
  }

}
