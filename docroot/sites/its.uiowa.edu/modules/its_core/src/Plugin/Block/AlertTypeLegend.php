<?php

namespace Drupal\its_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an 'Alert Type Legend' block.
 *
 * @Block(
 *   id = "alert_type_legend_block",
 *   admin_label = @Translation("Alert type legend"),
 *   category = @Translation("Site custom")
 * )
 */
class AlertTypeLegend extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['alert_type_legend'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [''],
      ],
    ];
    $build['alert_type_legend']['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'headline',
          'h6',
        ],
      ],
    ];

    $build['alert_type_legend']['heading']['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => 'Legend',
      '#attributes' => [
        'class' => ['headline__heading'],
      ],
    ];
    $build['alert_type_legend']['badges'] = [
      'wrapper' => [
        '#type' => 'markup',
        '#markup' =>
        '<p>' .
        '<span class="block-margin__top badge badge--orange">Outage</span> ' .
        '<span class="block-margin__top badge badge--green">Planned Maintenance</span> ' .
        '<span class="block-margin__top badge badge--blue">Service Degradation</span> ' .
        '<span class="block-margin__top badge badge--cool-gray">Service Announcement</span>' .
        '</p>',
      ],
    ];

    return $build;
  }

}
