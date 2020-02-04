<?php

/**
 * @file
 * Settings file for the theme.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function uids_base_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {
  if (isset($form_id)) {
    return;
  }

  $form['uids_base_settings'] = [
    '#type'         => 'details',
    '#title'        => t('Header settings'),
    '#description'  => t('Configure the overall type of header, the style of navigation to be used, and whether or not the header is sticky.'),
    '#weight' => -1000,
    '#open' => TRUE,
  ];
  $form['uids_base_settings']['header_type'] = [
    '#type' => 'select',
    '#title' => t('Header Type'),
    '#description' => t('Select an option'),
    '#options' => [
      'header--primary' => t('IOWA'),
      'header--secondary' => t('College'),
      'header--tertiary' => t('Department'),
    ],
    '#default_value' => theme_get_setting('header_type'),
  ];
  $form['uids_base_settings']['header_nav'] = [
    '#type' => 'select',
    '#title' => t('Header navigation style'),
    '#description' => t('Select an option'),
    '#options' => [
      'nav--toggle' => t('Toggle navigation'),
      'nav--horizontal' => t('Horizontal navigation'),
    ],
    '#default_value' => theme_get_setting('header_nav'),
  ];
  $form['uids_base_settings']['header_sticky'] = [
    '#type' => 'checkbox',
    '#title' => t('Sticky header'),
    '#description' => t('Select an option'),
    '#default_value' => theme_get_setting('header_sticky'),
  ];
}
