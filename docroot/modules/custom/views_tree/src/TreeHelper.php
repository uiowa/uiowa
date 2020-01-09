<?php

namespace Drupal\views_tree;

use Drupal\Core\Template\Attribute;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * The tree helper service.
 */
class TreeHelper {

  /**
   * The tree values service.
   *
   * @var \Drupal\views_tree\ViewsResultTreeValues
   */
  protected $treeValues;

  /**
   * Constructs the tree helper.
   *
   * @param \Drupal\views_tree\ViewsResultTreeValues $tree_values
   *   The tree values service.
   */
  public function __construct(ViewsResultTreeValues $tree_values) {
    $this->treeValues = $tree_values;
  }

  /**
   * Builds a render tree from an executed view.
   */
  public function buildRenderTree(ViewExecutable $view, array $rows) {
    $result = $view->result;
    $this->treeValues->setTreeValues($view, $result);
    $result_tree = $this->getTreeFromResult($result);
    return $this->applyFunctionToTree($result_tree, function (ResultRow $row) use ($rows) {
      return $rows[$row->index];
    });
  }

  /**
   * Adds hierarchical data attributes to the tree data.
   */
  public function addDataAttributes(TreeItem $tree, $nesting = 0) {
    $node = $tree->getNode();
    if (isset($node['attributes']) && $node['attributes'] instanceof Attribute) {
      $node['attributes']->setAttribute('data-hierarchy-level', $nesting);
    }
    $nesting++;
    foreach ($tree->getLeaves() as $leaf) {
      $this->addDataAttributes($leaf, $nesting);
    }
  }

  /**
   * Builds a tree from a views result.
   *
   * @param array $result
   *   The views results with views_tree_main and views_tree_parent set.
   *
   * @return \Drupal\views_tree\TreeItem
   *   A tree representation.
   */
  public function getTreeFromResult(array $result) {
    $groups = $this->groupResultByParent($result);
    return $this->getTreeFromGroups($groups);
  }

  /**
   * Get a tree from given groups.
   *
   * @param array $groups
   *   The groups.
   * @param string $current_group
   *   The current group.
   *
   * @return \Drupal\views_tree\TreeItem
   *   The tree for the given groups.
   */
  protected function getTreeFromGroups(array $groups, $current_group = '0') {
    $return = new TreeItem(NULL);

    if (empty($groups[$current_group])) {
      return $return;
    }

    foreach ($groups[$current_group] as $item) {
      $tree_item = new TreeItem($item);
      $return->addLeave($tree_item);
      $tree_item->setLeaves($this->getTreeFromGroups($groups, $item->views_tree_main)->getLeaves());
    }
    return $return;
  }

  /**
   * Groups results by parent.
   *
   * @param array $result
   *   The result set.
   *
   * @return array
   *   Result grouped by parent.
   */
  protected function groupResultByParent(array $result) {
    $return = [];

    foreach ($result as $row) {
      $return[$row->views_tree_parent][] = $row;
    }
    return $return;
  }

  /**
   * Applies a given callable to each row and leaf.
   *
   * @param \Drupal\views_tree\TreeItem $tree
   *   The tree item.
   * @param callable $callable
   *   The callable.
   *
   * @return \Drupal\views_tree\TreeItem
   *   The new tree item.
   */
  public function applyFunctionToTree(TreeItem $tree, callable $callable) {
    if (($node = $tree->getNode()) && $node !== NULL) {
      $new_node = $callable($tree->getNode());
    }
    else {
      $new_node = NULL;
    }
    $new_tree = new TreeItem($new_node);
    foreach ($tree->getLeaves() as $leave) {
      $new_tree->addLeave($this->applyFunctionToTree($leave, $callable));
    }
    return $new_tree;
  }

}
