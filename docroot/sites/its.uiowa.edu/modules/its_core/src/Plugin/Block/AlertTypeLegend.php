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
    $tids = ['406', '411', '416', '421'];
    $tcolor = [
      '406' => 'orange',
      '411' => 'green',
      '416' => 'blue',
      '421' => 'cool-gray'
    ];
    $entityTypeManager = \Drupal::entityTypeManager();
    $terms = $entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids);
    $badgeMarkup = '<p>';
    foreach ($terms as $term) {
      $name = $term->name->value;
      $description = trim(preg_replace('/\s\s+/', '',strip_tags($term->description->value)));
      $color = $tcolor[$term->tid->value];

      $badgeMarkup .= '<span class="block-margin__top badge badge--' . $color. '" title="' . $description .'">' . $name . '</span> ';
    }

    $badgeMarkup .= '</p>';

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
        '#markup' => $badgeMarkup,
      ],
    ];

    return $build;
  }

}
