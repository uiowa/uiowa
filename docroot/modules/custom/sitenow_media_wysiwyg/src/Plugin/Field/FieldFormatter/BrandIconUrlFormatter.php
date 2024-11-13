<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * BrandIcon URL field formatter.
 *
 * @FieldFormatter(
 *   id = "brand_icon_url_formatter",
 *   label = @Translation("Brand Icon"),
 *   description = @Translation("Display the brand icon svg instance."),
 *   field_types = {
 *     "brand_icon_url"
 *   }
 * )
 */
class BrandIconUrlFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $brand_icon_path = $item->uri;

      // Remove the "internal:" prefix if present.
      if (strpos($brand_icon_path, 'internal:') === 0) {
        $brand_icon_path = substr($brand_icon_path, 9);
      }

      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => $brand_icon_path,
          'alt' => $item->getValue('options')['alt'],
        ],
      ];
    }

    return $elements;
  }

}
