<?php

namespace Drupal\views_tree\Plugin\views\style;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;

/**
 * Style plugin to render each item as hierarchy.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "tree_entity_reference_selection",
 *   title = @Translation("TreeHelper (Adjacency model)"),
 *   help = @Translation("Display the results as a nested tree"),
 *   theme = "views_tree",
 *   display_types = {"entity_reference"},
 * )
 */
class TreeERSelection extends Tree {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['search_fields'] = ['default' => NULL];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $options = $this->displayHandler->getFieldLabels(TRUE);
    $form['search_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Search fields'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $this->options['search_fields'],
      '#description' => $this->t('Select the field(s) that will be searched when using the autocomplete widget.'),
      '#weight' => -3,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    if (!empty($this->view->live_preview)) {
      return parent::render();
    }

    // Group the rows according to the grouping field, if specified.
    $sets = $this->renderGrouping($this->view->result, $this->options['grouping']);

    // Grab the alias of the 'id' field added by
    // entity_reference_plugin_display.
    $id_field_alias = $this->view->storage->get('base_field');

    // @todo We don't display grouping info for now. Could be useful for select
    // widget, though.
    $results = [];
    foreach ($sets as $records) {
      foreach ($records as $values) {
        $results[$values->{$id_field_alias}] = $this->view->rowPlugin->render($values);
        // Sanitize HTML, remove line breaks and extra whitespace.
        $results[$values->{$id_field_alias}]['#post_render'][] = function ($html, array $elements) {
          return Xss::filterAdmin(preg_replace('/\s\s+/', ' ', str_replace("\n", '', $html)));
        };
      }
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    return TRUE;
  }

}
