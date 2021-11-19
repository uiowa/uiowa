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
    $counter = 0;
    // Right now our "id" is just a delta, but hopefully this will be
    // a uuid in the future for better comparisons.
    foreach ($field_items->getSections() as $id => $section) {
      // Starting off, let's just take care of the lb styles.
      // Create a simple prefix.
      $prefix = "Section " . $id . " Configuration: ";
      // Grab our lb styles, combine with our prefix, and add it to our results.
      $lb_styles = implode(', ', $section->toArray()['layout_settings']['layout_builder_styles_style']);
      $result[$counter++] = $prefix . $lb_styles;
      // Now let's process the actual content within the section.
      foreach ($section->getComponents() as $comp_id => $component) {
        $config = $component->get('configuration');
        // See if we're dealing with a custom block or not.
        if (!isset($config['block_revision_id'])) {
          // If we don't have a block_revision_id, see if
          // we're dealing with a listBlock, which is provided
          // by views rather than layout_builder.
          if (isset($config['provider']) && $config['provider'] === 'views') {
            $this->processListBlock($config, $counter, $result);
            $region = ucfirst($component->get('region'));
            // @todo Update this.
            $bundle = "List Block";
            $prefix = 'Section ' . $id . ', ' . $region . ' Region, ' . $bundle . ": \r";
            $result[$counter] = $prefix . $result[$counter];
            $counter++;
            continue;
          }
          // If we don't have a block_revision_id,
          // and we aren't a views block, then we're something else
          // and for now, we don't care. Pop out of this loop iteration.
          continue;
        }
        $region = ucfirst($component->get('region'));
        $rev_id = $config['block_revision_id'];
        if ($rev_id) {
          $block = $this->entityTypeManager
            ->getStorage('block_content')
            ->loadRevision($rev_id);
        }
        else {
          continue;
        }
        if ($block) {
          $bundle = ucwords($this->prettifyMachineName($block->bundle()));
          $prefix = 'Section ' . $id . ', ' . $region . ' Region, ' . $bundle . ": \r";
          $this->processBlock($block, $counter, $result);
        }
        $result[$counter] = $prefix . $result[$counter];
        $counter++;
        }
      }
    return $result;
  }

  protected function processBlock($block, $counter, &$result) {
    foreach ($block->toArray() as $arr_key => $arr_value) {
      if (str_starts_with($arr_key, 'field_')) {
        foreach ($arr_value as $field_num => $field) {
          foreach ($field as $value_key => $value_value) {
            $indexer = $this->generateIndexer($arr_key, $field_num, $value_key);
            if (is_array($value_value)) {
              $value_value = implode('.', $value_value);
            }
            $old = isset($result[$counter]) ? $result[$counter] : '';
            $result[$counter] = $old . "\r" . implode(': ', [
                $indexer,
                $value_value,
              ]);
          }
        }
      }
    }
  }

  protected function processListBlock($config, $counter, &$result) {
    $to_skip = [
      'id',
      'label',
      'provider',
      'label_display',
      'views_label',
    ];
    foreach ($config as $arr_key => $arr_value) {
      if (in_array($arr_key, $to_skip)) {
        continue;
      }
      if (is_array($arr_value)) {
        foreach ($arr_value as $field_name => $field_values) {
          // @todo Handle these.
          continue;
        }
      }
      else {
        $indexer = ucwords($this->prettifyMachineName($arr_key));
        $result[$counter] = implode(': ', [
            $indexer,
            $arr_value,
          ]);
      }
    }
  }

  protected function generateIndexer($arr_key, $field_num = 0, $value_key = '') {
    $field_col_name = ucwords($this->prettifyMachineName($arr_key));
    // Only include the number if we're on more than one field value,
    // and increment it for readability, rather than being zero-based.
    if ($field_num > 0) {
      $field_col_name = $field_col_name . ' ' . ++$field_num;
    }
    // Now to handle the individual fields.
    $field_name = ucwords($this->prettifyMachineName($value_key));
    return $field_col_name . ', ' . $field_name;
  }

  protected function prettifyMachineName($machine_name) {
    $exploded = explode('_', $machine_name);
    // Remove the starting 'field' if it's there.
    if (str_starts_with($machine_name, 'field')) {
      array_shift($exploded);
    }
    return implode(' ', $exploded);
  }

}
