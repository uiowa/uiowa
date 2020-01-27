<?php

namespace Drupal\Tests\views_tree\Kernel\Plugin\views\style;

use Drupal\views\Entity\View;
use Drupal\views\Views;

/**
 * Tests the views tree list style plugin.
 *
 * @group views_tree
 *
 * @coversDefaultClass \Drupal\views_tree\Plugin\views\style\TreeTable
 */
class TreeTableTest extends TreeTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['views_tree_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    // Change view display to use the tree table style.
    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = View::load('views_tree_test');
    $display =& $view->getDisplay('default');
    $display['display_options']['style']['type'] = 'tree_table';
    $display['display_options']['style']['options']['display_hierarchy_column'] = 'name';
    unset($display['display_options']['style']['options']['type']);
    unset($display['display_options']['style']['options']['collapsible_tree']);

    // Display the 'id' column so the table has more than a single column.
    $display['display_options']['fields']['id']['exclude'] = FALSE;
    $view->save();
  }

  /**
   * Tests the tree table style plugin.
   */
  public function testTreeTableStyle() {
    $view = Views::getView('views_tree_test');
    $this->executeView($view);
    $this->assertCount(15, $view->result);

    // Render the view, which will re-sort the result.
    // @see template_preprocess_views_tree_table()
    $output = $view->render('default');
    $rendered_output = \Drupal::service('renderer')->renderRoot($output);

    // Verify parents are properly set in the result.
    $result = $view->result;
    $this->assertEquals(1, $result[0]->views_tree_parent);
    $this->assertEquals(6, $result[11]->views_tree_parent);

    // Verify rendered output.
    $this->setRawContent($rendered_output);
    $rows = $this->xpath('//tbody/tr');
    $this->assertEquals(1, (string) $rows[0]->attributes()['data-hierarchy-level']);
    $this->assertEquals(2, (string) $rows[1]->attributes()['data-hierarchy-level']);
    $this->assertEquals(3, (string) $rows[6]->attributes()['data-hierarchy-level']);
    $this->assertEquals(1, (string) $rows[11]->attributes()['data-hierarchy-level']);

    // Verify the hierarchy display class is added to the correct cell.
    $this->assertContains('views-tree-hierarchy-cell', (string) $rows[0]->td->attributes()->class);
    $this->assertNotContains('views-tree-hierarchy-cell', (string) $rows[0]->td[1]->attributes()->class);
  }

}
