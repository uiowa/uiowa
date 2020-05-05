<?php

namespace Drupal\views_tree\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\EntityReferenceSelection\ViewsSelection;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views_tree\TreeHelper;
use Drupal\views_tree\ViewsResultTreeValues;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'selection' entity_reference.
 *
 * @EntityReferenceSelection(
 *   id = "views_tree",
 *   label = @Translation("TreeHelper (Adjacency model)"),
 *   group = "views_tree",
 *   weight = 0
 * )
 */
class TreeViewsSelection extends ViewsSelection {

  /**
   * The tree helper.
   *
   * @var \Drupal\views_tree\TreeHelper
   */
  protected $tree;

  /**
   * The views result tree values.
   *
   * @var \Drupal\views_tree\ViewsResultTreeValues
   */
  protected $viewsResultTreeValues;

  /**
   * Constructs the ER views selection plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\views_tree\TreeHelper $tree
   *   The tree helper.
   * @param \Drupal\views_tree\ViewsResultTreeValues $views_result_tree_values
   *   The views result tree values service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, TreeHelper $tree, ViewsResultTreeValues $views_result_tree_values) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_manager, $module_handler, $current_user);

    $this->tree = $tree;
    $this->viewsResultTreeValues = $views_result_tree_values;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('views_tree.tree'),
      $container->get('views_tree.views_tree_values')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $handler_settings = $this->configuration['handler_settings'];
    $display_name = $handler_settings['view']['display_name'];
    $arguments = $handler_settings['view']['arguments'];
    $result = [];
    if ($this->initializeView($match, $match_operator, $limit)) {
      // Get the results.
      $result = $this->view->executeDisplay($display_name, $arguments);
    }

    $this->applyTreeOnResult($this->view, $this->view->result);
    $tree = $this->tree->getTreeFromResult($this->view->result);

    $return = [];
    if ($result) {
      $this->tree->applyFunctionToTree($tree, function (ResultRow $row) use (&$return) {
        $entity = $row->_entity;
        $return[$entity->bundle()][$entity->id()] = str_repeat('-', $row->views_tree_depth) . $entity->label();
        return NULL;
      });
    }

    return $return;
  }

  /**
   * Applies a tree to a result set.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param \Drupal\views\ResultRow[] $result
   *   The result set.
   */
  protected function applyTreeOnResult(ViewExecutable $view, array $result) {
    $this->viewsResultTreeValues->setTreeValues($view, $result);
  }

}
