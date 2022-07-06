<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\sitenow_media_wysiwyg\Plugin\media\Source\StaticMap;

/**
 * StaticMap URL field formatter.
 *
 * @FieldFormatter(
 *   id = "static_map_url_formatter",
 *   label = @Translation("Static Map"),
 *   description = @Translation("Display the static map instance."),
 *   field_types = {
 *     "static_map_url"
 *   }
 * )
 */
class StaticMapUrlFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   * This builds the render for the map.
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $values = $items->getValue();
    foreach ($elements as $delta => $entity) {
      // Hardcoded values for now.
      // @todo wire these up to actual content...
      $location = str_replace('!m/', '', parse_url($values[0]['uri'], PHP_URL_FRAGMENT));
      $label = 'asdf';
      $zoom = 17;

      $elements[$delta] = [
        'map' => [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#attributes' => [
            'href' => $values[0]['uri'],
            'title' => $label,
            'aria-label' => 'View on maps.uiowa.edu',
          ],
          'static' => [
            '#type' => 'html_tag',
            '#tag' => 'img',
            '#attributes' => [
              'class' => 'static-map',
              'src' => urldecode("https://staticmap.concept3d.com/map/static-map/?map=1890&loc=" . $location . "&scale=2&label&zoom=" . $zoom),
//              'style' => "background-image: url(" . urldecode("https://staticmap.concept3d.com/map/static-map/?map=1890&loc=" . $location . "&scale=2&label&zoom=" . $zoom) . ");",
            ],
          ],
        ],
      ];
    }

    return $elements;
  }

}
