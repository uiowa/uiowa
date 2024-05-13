<?php

namespace Drupal\commencement_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Commencement venue directions link block.
 *
 * @Block(
 *   id = "directionslink_block",
 *   admin_label = @Translation("Directions Link Block"),
 *   category = @Translation("Restricted"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class DirectionsLink extends BlockBase {

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
    $geo_field = $node->get('field_geolocation');

    $build = [];

    // Check if the $geo_field has a value.
    if (!$geo_field->isEmpty()) {
      $geolocation = $geo_field->first()->getValue();

      if (!empty($geolocation['latlon'])) {
        $latlon_value = $geolocation['latlon'];

        $google_maps_link = 'https://www.google.com/maps/dir/?api=1&destination=' . $latlon_value;

        $link = [
          '#type' => 'link',
          '#title' => [
            '#markup' => 'Get Venue Directions <span role="presentation" class="fas fa-arrow-right"></span>',
          ],
          '#url' => Url::fromUri($google_maps_link),
          '#attributes' => [
            'target' => '_blank',
            'class' => [
              'bttn',
              'bttn--secondary',
              'bttn--small',
            ],
          ],
        ];

        $link_render_array = \Drupal::service('renderer')->render($link);

        $build['container']['link'] = [
          '#type' => 'markup',
          '#markup' => $link_render_array,
        ];
      }
    }

    return $build;
  }

}
