<?php

namespace Drupal\uiowa_maps\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'uiowa_maps_static_map_default' formatter.
 *
 * @FieldFormatter(
 *   id = "uiowa_maps_static_map_default",
 *   label = @Translation("Default"),
 *   field_types = {"uiowa_maps_static_map"}
 * )
 */
class StaticMapDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $element['#attached']['library'][] = 'uiowa_maps/uiowa_maps_static_map';
    foreach ($items as $delta => $item) {
      $location = str_replace('!m/', '', parse_url($item->link, PHP_URL_FRAGMENT));
      $element[$delta]['map'] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#attributes' => [
          'href' => $item->link,
          'title' => $item->label,
          'aria-label' => $item->label,
        ],
        'static' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => 'static-map',
            'style' => "background-image: url('https://staticmap.concept3d.com/map/static-map/?map=1890&loc=" . $location . "&scale=2&zoom=" . $item->zoom ."');",
          ],
        ],
      ];

    }

    return $element;
  }

}
