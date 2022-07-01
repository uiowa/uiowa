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
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $values = $items->getValue();

    foreach ($elements as $delta => $entity) {
      $parsed_url = UrlHelper::parse($values[$delta]['uri']);
      $location = Html::escape($parsed_url['query']['loc']);
      $label = 'asdf';
      $zoom = 17;

      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#attributes' => [
          'href' => $parsed_url,
          'title' => $label,
          'aria-label' => $label,
        ],
        'static' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => 'static-map',
            'style' => "background-image: url('https://staticmap.concept3d.com/map/static-map/?map=1890&loc=" . $location . "&scale=2&zoom=" . $zoom . "');",
          ],
        ],
      ];
    }

    return $elements;
  }

}
