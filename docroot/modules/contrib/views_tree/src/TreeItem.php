<?php

namespace Drupal\views_tree;

/**
 * Defines a tree item class.
 */
class TreeItem implements \IteratorAggregate {

  /**
   * The main node in the tree.
   *
   * @var mixed
   */
  protected $node;

  /**
   * Leaves of the main node.
   *
   * @var \Drupal\views_tree\TreeItem[]
   */
  protected $leaves = [];

  /**
   * Creates a new TreeItem instance.
   *
   * @param mixed $node
   *   The main tree node to set.
   * @param array $leaves
   *   An optional array of leaves to set.
   */
  public function __construct($node, array $leaves = []) {
    $this->setNode($node);
    $this->setLeaves($leaves);
  }

  /**
   * Get the tree node.
   *
   * @return mixed
   *   The tree node.
   */
  public function getNode() {
    return $this->node;
  }

  /**
   * Sets the node.
   *
   * @param mixed $node
   *   The node to set.
   */
  public function setNode($node) {
    $this->node = $node;
  }

  /**
   * Get the leaves.
   *
   * @return \Drupal\views_tree\TreeItem[]
   *   An array of tree item leaves.
   */
  public function getLeaves() {
    return $this->leaves;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return new \ArrayIterator($this->leaves);
  }

  /**
   * Sets the leaves.
   *
   * @param array $leaves
   *   An array of leaves. If they are not already an instance of
   *   \Drupal\views_tree\TreeItem, each one will be converted.
   *
   * @return $this
   *   The instance of the TreeItem.
   */
  public function setLeaves(array $leaves) {
    foreach ($leaves as &$leave) {
      if (!$leave instanceof static) {
        $leave = new TreeItem($leave);
      }
    }
    $this->leaves = $leaves;
    return $this;
  }

  /**
   * Adds a leaf.
   *
   * @param mixed $item
   *   An item to add. If not an instance of \Drupal\views_tree\TreeItem it will
   *   be converted.
   *
   * @return $this
   */
  public function addLeave($item) {
    if (!$item instanceof static) {
      $item = new TreeItem($item);
    }
    $this->leaves[] = $item;
    return $this;
  }

}
