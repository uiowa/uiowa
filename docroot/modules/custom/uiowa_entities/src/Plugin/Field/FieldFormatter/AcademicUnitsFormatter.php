<?php

namespace Drupal\uiowa_entities\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;

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
class AcademicUnitsFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($this->getEntitiesToView($items, 'en') as $delta => $entity) {
      $element[$delta] = [
        '#plain_text' => $entity->get('label'),
        '#url' => $entity->get('homepage'),
        '#type' => $entity->get('type'),
      ];
    }

    return $element;
  }

}
