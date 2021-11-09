<?php

namespace Drupal\layout_builder_custom\Plugin\Diff\Field;

use Drupal\diff\FieldDiffBuilderBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin to diff uiowa headline fields.
 *
 * @FieldDiffBuilder(
 *   id = "uiowa_headline_field_diff_builder",
 *   label = @Translation("Uiowa Headline Field Diff"),
 *   field_types = {
 *     "uiowa_headline"
 *   },
 * )
 */
class UiowaHeadlineFieldBuilder extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];
    // @todo Do the comparisons and such here.
    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        if (isset($values['value'])) {
          $value = $field_item->view(['label' => 'hidden']);
          $result[$field_key][] = $value;
        }
      }
    }
    return $result;
  }

}
