<?php

namespace Drupal\facilities_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Accessibility links block.
 *
 * @Block(
 *   id = "a11y_block",
 *   admin_label = @Translation("A11yLinks Block"),
 *   category = @Translation("Restricted"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class AccessibilityLinks extends BlockBase {

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
    $node = $this->getContextValue('node');
    $building_number = $node->get('field_building_number')->getString();

    $building_image = $node->get('field_building_image')->first()->get('entity')->getValue()->getFileUri();
    $building_image_alt = $node->get('field_building_image')->first()->alt;

    $links = [
      'accessibility' => [
        'label' => 'Accessibility Map',
        'icon' => 'wheelchair',
        'id' => 'b4389a15836343e2bd07899a67560a9f',
      ],
      'lactation_room' => [
        'label' => 'Lactation Rooms',
        'icon' => 'map-marker-alt',
        'id' => '72c9109323954a58abbde5dae8e6ee03',
      ],
      'hearing_loop' => [
        'label' => 'Hearing Loop Systems',
        'icon' => 'ear-deaf',
        'id' => '7c639bd4000b4d34979a3a3a628d7180',
      ],
      'gender_inclusive_restroom' => [
        'label' => 'Gender Inclusive Restrooms',
        'icon' => 'person-half-dress',
        'id' => 'a787689a1da843018156b2f2e97da119',
      ],
    ];

    $list_markup = '<ul class="fa-ul">';
    foreach ($links as $link) {
      $url = 'https://uiadmin.maps.arcgis.com/apps/webappviewer/index.html?id=' . $link['id'] . '&query=Buildings,BuildingNumber,' . $building_number;
      $list_markup .= '<li><span class="fa-li">
        <i class="fa-solid fa-' . $link['icon'] . ' "></i>
      </span> ' . '<a href="' . $url . '">' . $link['label'] . '</a></li>';
    }
    $list_markup .= '</ul>';

    $build = [];

    $attributes = [];
    $attributes['class'] = [
      'bg--white',
      'headline--serif',
      'media--medium',
      'media--widescreen',
    ];

    $build['container']['services'][] = [
      '#type' => 'card',
      '#attributes' => $attributes,
      '#title' => 'Accessibility Links',
      '#media' => [
        '#theme' => 'image',
        '#uri' => $building_image,
        '#alt' => $building_image_alt,
      ],
      '#content' => [
        '#markup' => $list_markup,
      ],
    ];

    return $build;

  }

}
