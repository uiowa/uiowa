<?php

namespace Drupal\layout_builder_custom\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'uiowa_headline_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "uiowa_headline_formatter",
 *   label = @Translation("Uiowa Headline Field Type Formatter"),
 *   field_types = {
 *     "uiowa_headline"
 *   }
 * )
 */
class UiowaHeadlineFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {

      $styles = [
        'default' => 'headline',
        'headline_bold_serif' => 'headline bold-headline bold-headline--serif',
        'headline_bold_serif_underline' => 'headline bold-headline bold-headline--serif bold-headline--underline',
      ];
      $hidden = ($item->get('hide_headline')->getValue()) ? ' sr-only' : '';
      $item_style = isset($styles[$item->get('headline_style')->getValue()]) ?
        $styles[$item->get('headline_style')->getValue()] . $hidden : $hidden;

      $element[$delta] = [
        '#theme' => 'uiowa_headline_field_type',
        '#text' => strip_tags($item->get('headline')->getValue()),
        '#size' => $item->get('heading_size')->getValue(),
        '#styles' => $item_style,
      ];
    }

    return $element;
  }

}
