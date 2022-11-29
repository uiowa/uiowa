<?php

namespace Drupal\uiowa_core\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Form\SiteInformationForm;

/**
 * Configure site information settings for this site.
 *
 * @phpstan-ignore-next-line
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
      ],
    ];

    $form['site_information']['parent']['site_parent_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $site_config->get('parent.name'),
      '#description' => $this->t('The official name of the parent organization.'),
      '#states' => [
        'required' => [
          ':input[name="has_parent"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['site_information']['parent']['site_parent_url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $site_config->get('parent.url'),
      '#description' => $this->t('URL of parent site.'),
      '#states' => [
        'required' => [
          ':input[name="has_parent"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $has_parent = $form_state->getValue('has_parent');
    if (!$has_parent) {
      $form_state
        ->setValueForElement($form['site_information']['parent']['site_parent_name'], NULL)
        ->setValueForElement($form['site_information']['parent']['site_parent_url'], NULL);
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save additional site information config.
    $this->config('system.site')
      ->set('has_parent', $form_state->getValue('has_parent'))
      ->set('parent.name', $form_state->getValue('site_parent_name'))
      ->set('parent.url', $form_state->getValue('site_parent_url'))
      // Make sure to save the configuration.
      ->save();

    // Pass the remaining values off to the original form that we have extended,
    // so that they are also saved.
    parent::submitForm($form, $form_state);
  }

}
