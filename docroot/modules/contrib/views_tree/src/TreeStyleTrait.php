<?php

namespace Drupal\views_tree;

use Drupal\Core\Form\FormStateInterface;

/**
 * Contains common code for list and table tree style displays.
 *
 * @property \Drupal\views\Plugin\views\display\DisplayPluginBase $displayHandler
 * @property array $options
 */
trait TreeStyleTrait {

  /**
   * Gather common options.
   */
  protected function defineCommonOptions(array &$options) {
    $options['main_field'] = ['default' => ''];
    $options['parent_field'] = ['default' => ''];
  }

  /**
   * Builds common form elements for the options form.
   *
   * @param array $form
   *   The form definition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function getCommonOptionsForm(array &$form, FormStateInterface $form_state) {
    $fields = ['' => $this->t('<None>')];

    foreach ($this->displayHandler->getHandlers('field') as $field => $handler) {
      $fields[$field] = $handler->adminLabel();
    }

    $form['main_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Main field'),
      '#options' => $fields,
      '#default_value' => $this->options['main_field'],
      '#description' => $this->t('Select the field with the unique identifier for each record.'),
      '#required' => TRUE,
    ];

    $form['parent_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent field'),
      '#options' => $fields,
      '#default_value' => $this->options['parent_field'],
      '#description' => $this->t("Select the field that contains the unique identifier of the record's parent."),
    ];

  }

}
