<?php

namespace Drupal\sitenow_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Sitenow Search settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_search_settings';
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
    $form['needle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Needle'),
      '#default_value' => '',
    ];
    $form['search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
      '#name' => 'search',
      '#submit' => [
        [$this, 'searchButton'],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * Perform a search with the given needle..
   */
  public function searchButton(array &$form, FormStateInterface $form_state) {
    // Grab all the fields.
    $fields = getAllTextFields();
    $needle = $form_state->getValue('needle');
    $results = searchFields($fields, $needle);
    return $form_state;
  }

}
