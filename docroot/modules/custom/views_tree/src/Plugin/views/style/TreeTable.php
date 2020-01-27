<?php

namespace Drupal\views_tree\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\Table;
use Drupal\views_tree\TreeStyleTrait;

/**
 * Defines a tree-based table display.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "tree_table",
 *   title = @Translation("Tree (table)"),
 *   help = @Translation("Displays the results as a nested tree in a table"),
 *   theme = "views_tree_table",
 *   display_types = {"normal"}
 * )
 */
class TreeTable extends Table {

  use TreeStyleTrait;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $this->defineCommonOptions($options);
    $options['display_hierarchy_column'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $this->getCommonOptionsForm($form, $form_state);

    // This is the column the hierarchy will actually be displayed in.
    $form['display_hierarchy_column'] = [
      '#type' => 'select',
      '#title' => $this->t('Hierarchy display column'),
      '#description' => $this->t('The table column in which to represent the hierarchy. This is typically a title/label field.'),
      '#required' => TRUE,
      '#options' => $this->displayHandler->getFieldLabels(),
      '#default_value' => $this->options['display_hierarchy_column'],
    ];
  }

}
