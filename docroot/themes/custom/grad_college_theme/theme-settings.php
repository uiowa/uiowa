<?php

/**
 * @file
 * Settings file for the theme.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function grad_college_theme_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {
  if (isset($form_id)) {
    return;
  }

  $form['collegiate_theme_settings']['collegiate_theme_header_color_settings']['#options'] = [
    'site-header__primary' => t('Gold Background, Black Text (default)'),
    'site-header--secondary' => t('Black Background, White Text'),
    'site-header__tertiary' => t('White Background, Black Text'),
  ];
}
