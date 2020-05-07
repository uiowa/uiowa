<?php

namespace Drupal\Tests\views_tree\Kernel\Plugin\views\style;

use Drupal\views\Views;

/**
 * Tests the views tree list style plugin.
 *
 * @group views_tree
 *
 * @coversDefaultClass \Drupal\views_tree\Plugin\views\style\Tree
 */
class TreeTest extends TreeTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['views_tree_test'];

  /**
   * Tests the tree style plugin.
   */
  public function testTreeStyle() {
    $view = Views::getView('views_tree_test');
    $this->executeView($view);
    $this->assertCount(15, $view->result);

    // Render the view, which will re-sort the result.
    // @see template_preprocess_views_tree()
    $output = $view->render('default');
    $rendered_output = \Drupal::service('renderer')->renderRoot($output);

    // Verify parents are properly set in the result.
    $result = $view->result;
    $this->assertEquals(1, $result[0]->views_tree_parent);
    $this->assertEquals(6, $result[11]->views_tree_parent);

    // Verify rendered output.
    $this->setRawContent($rendered_output);
    $rows = $this->xpath('//span[contains(@class, "field-content")]');
    $this->assertEquals('parent 1', (string) $rows[0]);
    $this->assertEquals('child 1 (parent 1)', (string) $rows[1]);
    $this->assertEquals('parent 2', (string) $rows[4]);
    $this->assertEquals('grand child 1 (c 1, p 2)', (string) $rows[6]);
    $this->assertEquals('parent 3', (string) $rows[11]);
  }

}
