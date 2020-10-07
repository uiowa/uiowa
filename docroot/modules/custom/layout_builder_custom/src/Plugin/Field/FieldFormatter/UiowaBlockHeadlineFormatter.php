<?php

namespace Drupal\layout_builder_custom\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'uiowa_block_headline_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "uiowa_block_headline_formatter",
 *   label = @Translation("Uiowa Block Headline Field Type Formatter"),
 *   field_types = {
 *     "uiowa_block_headline"
 *   }
 * )
 */
class UiowaBlockHeadlineFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {

      $styles = [
        'default' => '',
        'headline_bold_serif' => 'bold-headline bold-headline--serif',
        'headline_bold_serif_highlight' => 'bold-headline bold-headline--serif bold-headline--underline',
      ];
      $hidden = ($item->get('hide_headline')->getValue()) ? ' sr-only' : '';

      $element[$delta] = [
        '#theme' => 'uiowa_block_headline_field_type',
        '#text' => strip_tags($item->get('headline')->getValue()),
        '#size' => $item->get('heading_size')->getValue(),
        '#styles' => $styles[$item->get('headline_style')->getValue()] . $hidden,
      ];
    }

    return $element;
  }

}
