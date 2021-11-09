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
    $result = array();
    // @todo Do the comparisons and such here.
    return $result;
  }

}
