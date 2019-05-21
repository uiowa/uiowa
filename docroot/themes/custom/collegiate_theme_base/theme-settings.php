<?php

/**
 * @file
 * Settings file for the theme.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function collegiate_theme_base_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {
  if (isset($form_id)) {
    return;
  }

  $form['collegiate_theme_base_settings'] = [
    '#type'         => 'details',
    '#title'        => t('Header Settings'),
    '#description'  => t('Configure Site Name and Navigation Alignment'),
    '#weight' => -1000,
    '#open' => TRUE,
  ];
  $form['collegiate_theme_base_settings']['collegiate_theme_base_header_alignment_settings'] = [
    '#type' => 'select',
    '#title' => t('Header Alignment'),
    '#description' => t('Select an option'),
    '#options' => [
      'site-header__center' => t('Site name center, nav center'),
      'site-header__left' => t('Site name left, nav left'),
      'site-header__default' => t('Site name left, Nav right (default)'),
    ],
    '#default_value' => theme_get_setting('site-header__default'),
  ];
}
