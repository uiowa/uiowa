<?php

namespace Drupal\sitenow_intranet\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Sitenow Intranet settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_intranet_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sitenow_intranet.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sitenow_intranet.settings');
    $form['#tree'] = TRUE;

    $form['unauthorized'] = [
      '#type' => 'fieldset',
      '#description' => $this->t('The <em>Unauthorized</em> page will be displayed to users that have not logged in yet.'),
    ];

    $form['unauthorized']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unauthorized page title'),
      '#description' => $this->t('The title of the page.'),
      '#default_value' => $config->get('unauthorized.title'),
    ];

    $form['unauthorized']['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Unauthorized message'),
      '#description' => $this->t('The message shown on the page.'),
      '#default_value' => $config->get('unauthorized.message'),
      '#allowed_formats' => [
        'minimal',
      ],
    ];

    $form['access_denied'] = [
      '#type' => 'fieldset',
      '#description' => $this->t('The <em>Access denied</em> page will be displayed to users that have logged in but lack the proper role/permission to access the site.'),
    ];

    $form['access_denied']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access denied page title'),
      '#description' => $this->t('The title of the page.'),
      '#default_value' => $config->get('access_denied.title'),
    ];

    $form['access_denied']['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Access denied message'),
      '#description' => $this->t('The message shown on the page.'),
      '#default_value' => $config->get('access_denied.message'),
      '#allowed_formats' => [
        'minimal',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('sitenow_intranet.settings')
      ->set('unauthorized.title', $form_state->getValue([
        'unauthorized',
        'title',
      ]))
      ->set('unauthorized.message', $form_state->getValue([
        'unauthorized',
        'message',
        'value',
      ]))
      ->set('access_denied.title', $form_state->getValue([
        'access_denied',
        'title',
      ]))
      ->set('access_denied.message', $form_state->getValue([
        'access_denied',
        'message',
        'value',
      ]))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
