<?php

namespace Drupal\sitenow_events;

/**
 * Class Utilities.
 */
class Utilities {

  /**
   * Constructs a new Utilities object.
   */
  public function __construct() {}

  /**
   * Helper function to build the options tree.
   *
   * @param array $data
   *   Array of data to be sorted into a tree.
   * @param int $parent
   *   Stores the current id.
   *
   * @return array
   *   Returns an associative array options tree.
   *
   * @todo https://github.com/uiowa/uiowa/issues/5028
   */
  public function options_tree(array $data, int $parent = 0): array {
    $tree = [];

    foreach ($data as $d) {
      if ((int) $d['parent_id'] === $parent) {
        $children = $this->options_tree($data, $d['id']);

        if (!empty($children)) {
          $d['_children'] = $children;
        }
        $tree[] = $d;
      }
    }

    return $tree;
  }

  /**
   * Helper function to output the options array.
   *
   * @param array $tree
   *   Array of tree data to be printed.
   * @param int $r
   *   Basic counter.
   * @param int $p
   *   Parent id.
   * @param array $options
   *   Options array to be passed recursively.
   *
   * @return array
   *   Return options with children prefixed with dashes.
   *
   * @todo https://github.com/uiowa/uiowa/issues/5028
   */
  public function options_list(array $tree, $r = 0, $p = NULL, array &$options = []): array {
    foreach ($tree as $t) {
      $dash = ((int) $t['parent_id'] === 0) ? '' : str_repeat('-', $r) . ' ';
      $options[$t['id']] = $dash . $t['name'];

      if ((int) $t['parent_id'] === $p) {
        // Reset $r.
        $r = 0;
      }

      if (isset($t['_children'])) {
        $this->options_list($t['_children'], ++$r, $t['parent_id'], $options);
      }
    }

    return $options;
  }

}
