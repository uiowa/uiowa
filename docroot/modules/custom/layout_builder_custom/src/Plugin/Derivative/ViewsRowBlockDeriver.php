<?php

namespace Drupal\layout_builder_custom\Plugin\Derivative;

use Drupal\views\Plugin\Derivative\ViewsBlock;

/**
 * Provides block plugin definitions for Views block display rows.
 *
 * @see \Drupal\views\Plugin\Block\ViewsBlock
 */
class ViewsRowBlockDeriver extends ViewsBlock {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $parent_derivatives = parent::getDerivativeDefinitions($base_plugin_definition);
    $derivatives = [];
    foreach ($parent_derivatives as $view_delta => $derivative) {
      [$name, $display_id] = explode('-', $view_delta, 2);
      // $view = $this->viewStorage->load($name);
      /** @var \Drupal\views\ViewExecutable $executable */
      // $executable = $view->getExecutable();
      // $display = $executable->preview($display_id);
      // $total_item = $executable->getPager()->getTotalItems();
      // $per_page = $executable->getPager()->getItemsPerPage();
      $cardinality = 3;
      // Check if pager type is set to 'some'
      // If so, get 'items per page' and use that as the max.
      // Otherwise, use some arbitrary max value.
      // Check if pager type is set to 'some' and if so, get 'items per page'.
      for ($delta = 0; $delta < $cardinality; $delta++) {
        $plugin_id = "$view_delta:$delta";
        $derivatives[$plugin_id] = [
          'admin_label' => $parent_derivatives[$view_delta]['admin_label'] . t('(@delta)', [
            '@delta' => $delta,
          ]),
          'category' => $parent_derivatives[$view_delta]['category'],
        ];

        $derivatives[$plugin_id] += $parent_derivatives[$view_delta];
      }
    }

    return $derivatives;
  }

}
