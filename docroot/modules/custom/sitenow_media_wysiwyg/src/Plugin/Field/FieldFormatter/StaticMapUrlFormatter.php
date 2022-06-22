<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\sitenow_media_wysiwyg\Plugin\media\Source\StaticMap;

/**
 * Static map URL field formatter.
 *
 * @FieldFormatter(
 *   id = "static_map_url_formatter",
 *   label = @Translation("Static Map"),
 *    *   description = @Translation("Display the static map."),
 *   field_types = {
 *    "static_map_url"
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
      $id = Html::escape($parsed_url['query']['id']);

      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#attributes' => [
          'href' => $items->link,
          'title' => $items->label,
          'aria-label' => $items->label,
        ],
        'static' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => 'static-map',
            'style' => "background-image: url('https://staticmap.concept3d.com/map/static-map/?map=1890&loc=" . $items->location . "&scale=2&zoom=" . $items->zoom . "');",
          ],
        ],
      ];
    }

    return $elements;
  }

}
