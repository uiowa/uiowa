<?php

namespace Drupal\sitenow_p2lb\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Primary paragraphs2layoutbuilder class.
 */
class P2LbSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_p2lb_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings let you configure and use SiteNow paragraphs2layoutbuilder on this site.</p>'),
    ];

    // Grab all nodes that currently have paragraphs associated with them.
    $nids_w_paragraphs = sitenow_p2lb_paragraph_nodes();

    // Set the key=>value to use the nid for both.
    $nids_w_paragraphs = array_combine($nids_w_paragraphs, $nids_w_paragraphs);

    $form['nodes_w_paragraphs'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Nodes with paragraph items.'),
      '#options' => $nids_w_paragraphs,
    ];

    $form['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#name' => 'delete',
      '#submit' => [
        [$this, 'deleteButton'],
      ],
    ];

    $form['update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'update',
      '#submit' => [
        [$this, 'updateButton'],
      ],
    ];

    // @todo Original submit button currently doesn't do anything.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Add extra functionality here, if needed, else delete.
    parent::submitForm($form, $form_state);
  }

  /**
   * Delete connected paragraphs from the selected nodes.
   */
  public function deleteButton(array &$form, FormStateInterface $form_state) {
    // Grab nids for all boxes that were checked (0s are filtered out).
    $nids = array_filter(array_values($form_state->getValue('nodes_w_paragraphs')));
    foreach ($nids as $nid) {
      sitenow_p2lb_remove_attached_paragraphs($nid);
    }
    return $form_state;
  }

  /**
   * Update paragraphs to lb blocks from the selected nodes.
   */
  public function updateButton(array &$form, FormStateInterface $form_state) {
    // Grab nids for all boxes that were checked (0s are filtered out).
    $nids = array_filter(array_values($form_state->getValue('nodes_w_paragraphs')));
    foreach ($nids as $nid) {
      sitenow_p2lb_node_p2lb($nid);
    }
    // @todo Option to remove paragraphs after migrate, or review first?
    return $form_state;
  }

}
