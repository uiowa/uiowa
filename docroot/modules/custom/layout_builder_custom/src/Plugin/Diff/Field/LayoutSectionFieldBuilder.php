<?php

namespace Drupal\layout_builder_custom\Plugin\Diff\Field;

use Drupal\diff\FieldDiffBuilderBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin to diff layout section fields.
 *
 * @FieldDiffBuilder(
 *   id = "layout_section_field_diff_builder",
 *   label = @Translation("Layout Section Field Diff"),
 *   field_types = {
 *     "layout_section"
 *   },
 * )
 */
class LayoutSectionFieldBuilder extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];
    // @todo Do the comparisons and such here.
    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items->getSections() as $id => $section) {
      $result[$id] = implode(',', $section->toArray()['layout_settings']['layout_builder_styles_style']);
      foreach ($section->getComponents() as $comp_id => $component) {
        $config = $component->get('configuration');
        if (!isset($config['block_revision_id'])) {
          continue;
        }
        $rev_id = $config['block_revision_id'];
        $block = $this->entityTypeManager->getStorage('block_content')->loadRevision($rev_id);
        if ($block) {
          foreach ($block->toArray() as $arr_key => $arr_value) {
            if (str_starts_with($arr_key, 'field_')) {
              foreach ($arr_value as $field_num => $field) {
                foreach ($field as $value_key => $value_value) {
                  $indexer = implode('.', [$arr_key, $field_num, $value_key]);
                  if (is_array($value_value)) {
                    $value_value = implode('.', $value_value);
                  }
                  $result[$id] .= "\t | " . implode("\t | \t", [
                    $comp_id,
                    $indexer,
                    $value_value,
                  ]);
                }
              }
            }
          }
        }
      }
    }
    return $result;
  }

}
