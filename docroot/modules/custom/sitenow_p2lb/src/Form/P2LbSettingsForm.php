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

    $nids_w_paragraphs = sitenow_p2lb_paragraph_nodes();
    $form['nodes_w_paragraphs'] = [
      '#type' => 'checkboxes',
      '#title' => t('Nodes with paragraph items.'),
      '#options' => $nids_w_paragraphs,
    ];

    $form['delete'] = [
      '#type' => 'button',
      '#value' => t('Delete'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

}
