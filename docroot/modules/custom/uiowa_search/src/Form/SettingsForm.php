<?php

namespace Drupal\uiowa_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Uiowa Search settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_search_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_search.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('uiowa_search.settings');

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings allows you to customize the top University of Iowa search box.</p>'),
    ];
    $form['display_search'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display search box'),
      '#default_value' => $config->get('uiowa_search.display_search'),
    ];
    $form['display_search_all_uiowa'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the "Search all University of Iowa for ..." link'),
      '#default_value' => $config->get('uiowa_search.display_search_all_uiowa') ?? TRUE,
    ];
    $form['cse_engine_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Engine ID'),
      '#default_value' => $config->get('uiowa_search.cse_engine_id'),
      '#description' => $this->t('Enter the CSE Engine ID. The default is 015014862498168032802:ben09oibdpm.'),
      '#size' => 60,
      '#required' => TRUE,
    ];
    $form['cse_scope'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit Custom Search to this Site'),
      '#default_value' => $config->get('uiowa_search.cse_scope'),
      '#description' => $this->t('If checked, the Google Custom Search will be scoped to this site only. If you are using a CSE ID that includes multiple sites in it, you will likely want to uncheck this.'),
      '#size' => 60,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('uiowa_search.settings')
      ->set('uiowa_search.cse_engine_id', $form_state->getValue('cse_engine_id'))
      ->set('uiowa_search.cse_scope', $form_state->getValue('cse_scope'))
      ->set('uiowa_search.display_search', $form_state->getValue('display_search'))
      ->set('uiowa_search.display_search_all_uiowa', $form_state->getValue('display_search_all_uiowa'))
      ->save();
    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
