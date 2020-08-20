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
 *     "field_uiowa_block_headline"
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
          $element[$delta] = [
              'uiowa_block_headline' => ['#markup' => '<h2>Test</h2>'],
              '#theme' => 'uiowa_block_headline_field_type',
          ];
      }
      return $element;
  }
}
