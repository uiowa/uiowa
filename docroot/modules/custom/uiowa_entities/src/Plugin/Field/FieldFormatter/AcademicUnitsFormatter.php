<?php

namespace Drupal\uiowa_entities\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'uiowa_academic_units_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "uiowa_academic_units_formatter",
 *   label = @Translation("Uiowa Academic Units Reference Field Formatter"),
 *   field_types = {
 *     "uiowa_academic_units"
 *   }
 * )
 */
class AcademicUnitsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#plain_text' => $item->get('label'),
        '#url' => $item->get('homepage')->getValue(),
        '#type' => $item->get('type')->getValue(),
      ];
    }

    return $element;
  }

}
