<?php

namespace Drupal\uiowa_core\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Form\SiteInformationForm;

/**
 * Configure site information settings for this site.
 */
class UiowaCoreSiteInformationForm extends SiteInformationForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve the system.site configuration.
    $site_config = $this->config('system.site');

    // Get the original form from the class we are extending.
    $form = parent::buildForm($form, $form_state);

    $form['site_information']['has_parent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This site is part of a parent organization.'),
      '#default_value' => $site_config->get('has_parent'),
      '#description' => $this->t('Show additional options for setting the parent organization website. Note: this setting is not necessary to show that a site is part of the University of Iowa.'),
    ];

    $form['site_information']['parent'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="has_parent"]' => [
            'checked' => TRUE,
          ],
        ],
      ]
    ];

    $form['site_information']['parent']['site_parent_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $site_config->get('parent.label'),
      '#description' => $this->t('The official name of the parent organization.'),
    ];

    $form['site_information']['parent']['site_parent_canonical_uri'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $site_config->get('parent.canonical_uri'),
      '#description' => $this->t('URL of parent site.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Now we need to save the new description to the
    // system.site.description configuration.
    $this->config('system.site')
      ->set('has_parent', $form_state->getValue('has_parent'))
      ->set('parent.label', $form_state->getValue('site_parent_label'))
      ->set('parent.canonical_uri', $form_state->getValue('site_parent_canonical_uri'))
      // Make sure to save the configuration.
      ->save();

    // Pass the remaining values off to the original form that we have extended,
    // so that they are also saved.
    parent::submitForm($form, $form_state);
  }

}
