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
      $headline = $item->get('headline')->getValue();
      $heading_size = $item->get('heading_size')->getValue();
      $markup = (empty($headline)) ? NULL : '<' . $heading_size . '>' . $headline . '</' . $heading_size . '>';
      $element[$delta] = [
        'uiowa_block_headline' => ['#markup' => $markup],
        '#theme' => 'uiowa_block_headline_field_type',
      ];
    }

    return $element;
  }

}
