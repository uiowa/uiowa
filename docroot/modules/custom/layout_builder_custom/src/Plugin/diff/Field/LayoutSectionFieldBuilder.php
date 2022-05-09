<?php

namespace Drupal\layout_builder_custom\Plugin\diff\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\diff\FieldDiffBuilderBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\layout_builder\Section;

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
      if ($lb_styles = $this->processSectionStyles($id, $section)) {
        // Add the section styles and increment the counter
        // so that the styles will be diffed and displayed
        // on their own results line.
        $result[$counter++] = $lb_styles;
      };
      // Now let's process the actual content within the section.
      foreach ($section->getComponents() as $component) {
        $config = $component->get('configuration');
        // See if we're dealing with a custom block or not.
        if (!isset($config['block_revision_id'])) {
          // If we don't have a block_revision_id, see if
          // we're dealing with a listBlock, which is provided
          // by views rather than layout_builder.
          if (isset($config['provider']) && $config['provider'] === 'views') {
            $this->processListBlock($config, $counter, $result);
            // Grab which section region we're in,
            // as well as the specific list block bundle
            // and create our prefix label.
            $region = ucfirst($component->get('region'));
            $bundle = $component->getPlugin()->label();
            $prefix = 'Section ' . $id . ', ' . $region . ' Region, ' . $bundle . ": \r";
            $result[$counter] = $prefix . $result[$counter];
            $counter++;
            // After appending the result, we're done.
            // Move to the next loop iteration.
            continue;
          }
          // If we don't have a block_revision_id,
          // and we aren't a views block, then we're something else
          // and for now, we don't care. Pop out of this loop iteration.
          continue;
        }
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
          $region = ucfirst($component->get('region'));
          $bundle = ucwords($this->prettifyMachineName($block->bundle()));
          $prefix = 'Section ' . $id . ', ' . $region . ' Region, ' . $bundle . ": \r";
          $this->processBlock($block, $counter, $result);
          $result[$counter] = $prefix . $result[$counter];
          $counter++;
        }
      }
    }
    return $result;
  }

  /**
   * Helper function for pulling out the layout builder styles.
   *
   * @param int $id
   *   The section delta.
   * @param \Drupal\layout_builder\Section $section
   *   The section to process.
   *
   * @return false|string
   *   The resultant styles, or false if they aren't present.
   */
  protected function processSectionStyles(int $id, Section $section) {
    // Grab our lb styles, combine with our prefix, and add it to our results.
    $section_array = $section->toArray();
    // If we have layout_builder_styles,
    // grab them and append them to the results.
    // Increment the counter, so that they'll be displayed separately
    // from the section components.
    if (isset($section_array['layout_settings']) &&
      isset($section_array['layout_settings']['layout_builder_styles_style'])) {
      // Remove empty styles.
      $lb_styles = array_filter($section_array['layout_settings']['layout_builder_styles_style']);
      $lb_styles = implode(', ', $lb_styles);
      // Create a simple prefix.
      $prefix = "Section " . $id . " Configuration: ";
      return $prefix . $lb_styles;
    }
    return FALSE;
  }

  /**
   * Helper function for processing inline blocks.
   *
   * @param \Drupal\Core\Entity\EntityInterface $block
   *   The block to be processed.
   * @param int $counter
   *   The result counter.
   * @param array $result
   *   The final results array.
   */
  protected function processBlock(EntityInterface $block, int $counter, array &$result) {
    foreach ($block->toArray() as $arr_key => $arr_value) {
      // There's a lot of extra stuff in the block array.
      // Only look at the field_ labelled fields.
      if (str_starts_with($arr_key, 'field_')) {
        // Field values are arrays indexed by delta, which
        // allows for both single- and multi-valued fields.
        // Iterate through them.
        foreach ($arr_value as $field_num => $field) {
          foreach ($field as $value_key => $value_value) {
            // The value key isn't very helpful if it's just "value,"
            // so if it is, go ahead and drop it.
            $value_key = ($value_key == 'value') ? '' : $value_key;
            $indexer = $this->generateIndexer($arr_key, $field_num, $value_key);
            // If we're still dealing with an array,
            // combine all values into a single string.
            if (is_array($value_value)) {
              $value_value = implode('.', $value_value);
            }
            // We need to remove newlines added to formatted text areas.
            // They will break the results formatting if not removed.
            $value_value = preg_replace("|\n|", "", $value_value);
            // Check if we're building onto an existing result row,
            // or if we're starting a new one off of an empty string.
            $old = $result[$counter] ?? '';
            $result[$counter] = $old . "\r" . implode(': ', [
              $indexer,
              $value_value,
            ]);
          }
        }
      }
    }
  }

  /**
   * Helper function for processing List Blocks.
   *
   * @param array $config
   *   The component config array.
   * @param int $counter
   *   The result counter.
   * @param array $result
   *   The final results array.
   */
  protected function processListBlock(array $config, int $counter, array &$result) {
    // List blocks aren't built with field_ keys,
    // so we create a list of keys we know
    // we can skip for diffing purposes.
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
      // Treat the field differently if it's an array
      // or single value.
      if (is_array($arr_value)) {
        foreach ($arr_value as $field_name => $field_values) {
          // If it's a single value here, wrap it in an array,
          // and process alongside values that were already arrays.
          if (!is_array($field_values)) {
            $field_values = ['value' => $field_values];
          }
          foreach ($field_values as $value_key => $value_value) {
            // The value key isn't very helpful if it's just "value,"
            // so if it is, go ahead and drop it.
            $value_key = ($value_key == 'value') ? '' : $value_key;
            $indexer = ucwords($this->generateIndexer($field_name, 0, $value_key));
            $old = $result[$counter] ?? '';
            $result[$counter] = $old . "\r" . implode(': ', [
              $indexer,
              $value_value,
            ]);
          }
        }
      }
      else {
        // If the original array key wasn't an array,
        // then we can simply create an indexer and append.
        $indexer = ucwords($this->prettifyMachineName($arr_key));
        $old = $result[$counter] ?? '';
        $result[$counter] = $old . "\r" . implode(': ', [
          $indexer,
          $arr_value,
        ]);
      }
    }
  }

  /**
   * Helper function to create and indexer string for the final results.
   *
   * @param string $arr_key
   *   The array key for the indexed value.
   * @param int $field_num
   *   The delta for the keyed value.
   * @param string $value_key
   *   The specific field value key.
   *
   * @return string
   *   The indexer to be used in the final results.
   */
  protected function generateIndexer(string $arr_key, int $field_num = 0, string $value_key = '') {
    $field_col_name = ucwords($this->prettifyMachineName($arr_key));
    // Only include the number if we're on more than one field value,
    // and increment it for readability, rather than being zero-based.
    if ($field_num > 0) {
      $field_col_name = $field_col_name . ' ' . ++$field_num;
    }
    // If we don't have individual fields within the fieldset,
    // then we're done.
    if (empty($value_key)) {
      return $field_col_name;
    }
    // Now to handle the individual fields.
    $field_name = ucwords($this->prettifyMachineName($value_key));
    return $field_col_name . ', ' . $field_name;
  }

  /**
   * Simple helper to make machine names more user friendly.
   *
   * @param string $machine_name
   *   The string to pretty print.
   *
   * @return string
   *   The more readable string.
   */
  protected function prettifyMachineName(string $machine_name) {
    // Drop 'field' and 'uiowa' designators,
    // which don't really add anything for the editor.
    $machine_name = preg_replace('@(field\_)|(uiowa\_)@', '', $machine_name);
    // Replace underscores with spaces.
    $machine_name = preg_replace('|\_|', ' ', $machine_name);
    return $machine_name;
  }

}
