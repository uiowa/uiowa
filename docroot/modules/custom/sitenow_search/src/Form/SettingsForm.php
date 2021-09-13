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
    $form['regexed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('REGEXP?'),
      '#default_value' => 0,
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

    // Unset the original, currently unused submit button.
    // It might be used at another time if settings are needed.
    unset($form['actions']['submit']);
    return $form;
  }

  /**
   * Perform a search with the given needle..
   */
  public function searchButton(array &$form, FormStateInterface $form_state) {
    // Grab all the fields.
    $fields = get_all_text_fields();
    $needle = $form_state->getValue('needle');
    $regexed = $form_state->getValue('regexed');
    $results = search_fields($fields, $needle, $regexed);
    return $form_state;
  }

}
