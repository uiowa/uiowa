<?php

/**
 * @file
 * Theme settings for uiowa_bootstrap.
 */

use Drupal\Component\Serialization\Yaml;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function uiowa_bootstrap_form_system_theme_settings_alter(&$form, &$form_state, $form_id = NULL) {
  // Work-around for a core bug affecting admin themes. See issue #943212.
  if (isset($form_id)) {
    return;
  }
  $form['uib_libraries_container'] = [
    '#type' => 'details',
    '#title' => t('Libraries'),
    '#open' => FALSE,
  ];
  $path = drupal_get_path('theme', 'uiowa_bootstrap') . '/uiowa_bootstrap.libraries.yml';
  $file_contents = file_get_contents($path);
  $libraries = Yaml::decode($file_contents);
  $options = [];
  foreach ($libraries as $name => $data) {
    $options[$name] = $name;
  }
  $form['uib_libraries_container']['uib_libraries'] = [
    '#type' => 'checkboxes',
    '#options' => $options,
    '#title' => t('Include libraries'),
    '#default_value' => theme_get_setting('uib_libraries') ?? [],
  ];
}
