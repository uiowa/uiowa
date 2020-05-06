<?php

namespace Drupal\Tests\views_tree\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\views_tree\TreeItem;

/**
 * @coversDefaultClass \Drupal\views_tree\TreeItem
 * @group views_tree
 */
class TreeItemTest extends UnitTestCase {

  /**
   * @covers ::getIterator
   */
  public function testIterator() {
    $tree = new TreeItem(NULL);
    $tree
      ->addLeave(1)
      ->addLeave(2)
      ->addLeave(3);

    $this->assertEquals([new TreeItem(1), new TreeItem(2), new TreeItem(3)], iterator_to_array($tree));
  }

}
