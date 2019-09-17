<?php

/**
 * @file
 * Settings file for the theme.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function collegiate_theme_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {
  if (isset($form_id)) {
    return;
  }

  $form['collegiate_theme_settings'] = [
    '#type'         => 'details',
    '#title'        => t('Header Settings'),
    '#description'  => t('Configure Color, Site Name and Navigation Alignment'),
    '#weight' => -1000,
    '#open' => TRUE,
  ];
  $form['collegiate_theme_settings']['collegiate_theme_header_alignment_settings'] = [
    '#type' => 'select',
    '#title' => t('Header Alignment'),
    '#description' => t('Select an option'),
    '#options' => [
      'site-header__default' => t('Site name left, Nav right (default)'),
      'site-header__center' => t('Site name center, nav center'),
      'site-header__left' => t('Site name left, nav left'),
    ],
    '#default_value' => theme_get_setting('site-header__default'),
  ];
  $form['collegiate_theme_settings']['collegiate_theme_header_color_settings'] = [
    '#type' => 'select',
    '#title' => t('Header Color'),
    '#description' => t('Select an option'),
    '#options' => [
      'site-header--secondary' => t('Black Background, White Text (default)'),
      'site-header--primary' => t('Gold Background, Black Text'),
      'site-header--tertiary' => t('White Background, Black Text'),
    ],
    '#default_value' => theme_get_setting('site-header--secondary'),
  ];
  $form['collegiate_theme_settings']['collegiate_theme_container_settings'] = [
    '#type' => 'select',
    '#title' => t('Container Settings'),
    '#description' => t('Select an option'),
    '#options' => [
      'page__container' => t('Container (default)'),
      'page__container--full' => t('Full Width'),
      'page__container--mixed' => t('Full Width (Header Only)'),
    ],
    '#default_value' => theme_get_setting('page__container'),
  ];
}
