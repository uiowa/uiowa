<?php

/**
 * @file
 * Theme settings form for Hawkeye theme.
 */

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function hawkeye_form_system_theme_settings_alter(&$form, &$form_state) {

  $form['hawkeye'] = [
    '#type' => 'details',
    '#title' => t('Hawkeye'),
    '#open' => TRUE,
  ];

  $form['hawkeye']['font_size'] = [
    '#type' => 'number',
    '#title' => t('Font size'),
    '#min' => 12,
    '#max' => 18,
    '#default_value' => theme_get_setting('font_size'),
  ];

}
