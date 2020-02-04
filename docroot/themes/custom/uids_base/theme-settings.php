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

  $form['header'] = [
    '#type'         => 'details',
    '#title'        => t('Header settings'),
    '#description'  => t('Configure the overall type of header, the style of navigation to be used, and whether or not the header is sticky.'),
    '#weight' => -1000,
    '#open' => TRUE,
    '#tree' => TRUE,
  ];
  $form['header']['type'] = [
    '#type' => 'select',
    '#title' => t('Header Type'),
    '#description' => t('Select an option'),
    '#options' => [
      'header--primary' => t('IOWA'),
      'header--secondary' => t('College'),
      'header--tertiary' => t('Department'),
    ],
    '#default_value' => theme_get_setting('header.type'),
  ];
  $form['header']['nav_style'] = [
    '#type' => 'select',
    '#title' => t('Header navigation style'),
    '#description' => t('Select an option'),
    '#options' => [
      'nav--toggle' => t('Toggle navigation'),
      'nav--horizontal' => t('Horizontal navigation'),
    ],
    '#default_value' => theme_get_setting('header.nav_style'),
  ];
  $form['header']['sticky'] = [
    '#type' => 'checkbox',
    '#title' => t('Sticky header'),
    '#description' => t('Select an option'),
    '#default_value' => theme_get_setting('header.sticky'),
  ];
  $form['layout'] = [
    '#type' => 'details',
    '#title' => t('Layout options'),
    '#description' => t('Choose different layout options.'),
    '#weight' => -999,
    '#open' => TRUE,
    '#tree' => TRUE,
  ];
  $form['layout']['container'] = [
    '#type' => 'select',
    '#title' => t('Container Settings'),
    '#description' => t('Select an option'),
    '#options' => [
      'page__container' => t('Container (default)'),
      'page__container--full' => t('Full Width'),
      'page__container--mixed' => t('Full Width (Header Only)'),
    ],
    '#default_value' => theme_get_setting('layout.container'),
  ];

  $form['theme_settings']['#open'] = FALSE;
  $form['favicon']['#open'] = TRUE;
}
