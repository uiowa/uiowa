<?php

namespace Drupal\its_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an 'Alert Type Legend' block.
 *
 * @Block(
 *   id = "alert_type_legend_block",
 *   admin_label = @Translation("Alert Type Legend"),
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
          'headline--highlight',
          'headline--uppercase',
          'h5',
        ],
      ],
    ];

    $build['alert_type_legend']['heading']['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => 'Alert types',
      '#attributes' => [
        'class' => ['headline__heading'],
      ],
    ];
    $build['alert_type_legend']['badges'] = [
      'wrapper' => [
        '#type' => 'markup',
        '#markup' =>
          '<div class="card__meta" >' .
            '<div class="field__item"><span class="block-margin__top badge badge--orange"><i class="svg-inline--fa fas fa-triangle-exclamation"></i>Outage</span></div>' .
            '<div class="field__item"><span class="block-margin__top badge badge--green">Planned Maintenance</span></div>' .
            '<div class="field__item"><span class="block-margin__top badge badge--blue"><i class="svg-inline--fa fas fa-arrow-trend-down"></i></svg>Service Degradation</span></div>' .
            '<div class="field__item"><span class="block-margin__top badge badge--cool-gray">Service Announcement</span></div>' .
          '</div>',
      ],
    ];

    return $build;
  }

}
