<?php

/**
 * @file
 * Settings file for the theme.
 */

use Drupal\block\Entity\Block;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function uids_base_form_system_theme_settings_alter(&$form, FormStateInterface $form_state): void {

  $config = \Drupal::config('system.site');
  $has_parent = $config->get('has_parent') ?: 0;
  $variables['site_name'] = $config->get('name');
  $name_length = strlen($variables['site_name']);

  $form['header'] = [
    '#type' => 'details',
    '#title' => t('IOWA bar settings'),
    '#description' => t('Configure the overall type of header, the style of navigation to be used, and whether or not the header is sticky.'),
    '#weight' => -1000,
    '#open' => TRUE,
    '#tree' => TRUE,
  ];

  $form['header']['type'] = [
    '#type' => 'select',
    '#title' => t('Site name display'),
    '#description' => t('Select an option'),
    '#options' => [
      'inline' => t('Display inline with the IOWA bar'),
      'below' => t('Display below the IOWA bar'),
    ],
    '#default_value' => theme_get_setting('header.type'),
  ];

  // If there is a parent organization or the name is longer than 43
  // characters, set the header type to disabled.
  if ($has_parent || $name_length > 43) {
    $description = '';
    $url = Url::fromRoute('system.site_information_settings')->toString();

    if (($has_parent) && ($name_length > 43)) {
      $description = t('This option is disabled because a parent organization was set on the <a href=":url">site settings page</a> and the site name exceeds the recommended character count of 43 characters.', [
        ':url' => $url,
      ]);
    }
    elseif ($name_length > 43) {
      $description = t('This option is disabled because the site name set on the <a href=":url">site settings page</a> exceeds the recommended character count of 43 characters.', [
        ':url' => $url,
      ]);
    }
    elseif ($has_parent) {
      $description = t('This option is disabled because a parent organization was set on the <a href=":url">site settings page</a>. When you have a parent organization, your site name will <em>always</em> display on the line below. You will need to remove the parent organization information to select another option.', [
        ':url' => $url,
      ]);
    }

    $form['header']['type']['#disabled'] = TRUE;
    $form['header']['type']['#default_value'] = 'below';
    $form['header']['type']['#description'] = $description;
  }

  $form['header']['nav_style'] = [
    '#type' => 'select',
    '#title' => t('Header navigation style'),
    '#description' => t('Select an option'),
    '#options' => [
      'toggle' => t('Toggle navigation'),
      'horizontal' => t('Horizontal navigation'),
      'mega' => t('Mega menu navigation'),
    ],
    '#default_value' => theme_get_setting('header.nav_style'),
  ];

  $form['header']['sticky'] = [
    '#type' => 'checkbox',
    '#title' => t('Sticky header'),
    '#description' => t('A sticky header will continue to be available as the user scrolls down the page. It will hide on scroll down and show when the user starts to scroll up.'),
    '#default_value' => theme_get_setting('header.sticky'),
    '#states' => [
      'visible' => [
        ':input[name="header[nav_style]"]' => [
          'value' => 'toggle',
        ],
      ],
    ],
  ];

  // Display scroll to top button functionality.
  $form['header']['toppage'] = [
    '#type' => 'checkbox',
    '#title' => t('Back to top button'),
    '#description' => t('A back to top button will be visible when the user scrolls down the page.'),
    '#default_value' => theme_get_setting('header.toppage'),
  ];

  $form['header']['branding_options'] = [
    '#type' => 'select',
    '#title' => t('Branding options'),
    '#description' => t('Select an option'),
    '#options' => [
      'iowa' => t('Iowa'),
      'ccom' => t('CCOM'),
      'regents' => t('Regents'),
    ],
    '#default_value' => theme_get_setting('header.branding_options'),
  ];

  $top_links_limit = theme_get_setting('header.top_links_limit');

  // Get limit, otherwise limit to 2.
  $form['header']['top_links_limit'] = [
    '#type' => 'number',
    '#title' => t('Top Links Limit'),
    '#access' => FALSE,
    '#default_value' => ($top_links_limit ? $top_links_limit : 2),
  ];

  // Change theme style.
  $form['style'] = [
    '#type' => 'details',
    '#title' => t('Color Palette'),
    '#description' => t('Configure the color palette for the uids_base theme.'),
    '#weight' => -1000,
    '#open' => TRUE,
    '#tree' => TRUE,
  ];

  $form['style']['style_selector'] = [
    '#type' => 'select',
    '#title' => t('Style'),
    '#description' => t('This option changes the primary gold theme color.'),
    '#options' => [
      'brand' => t('Iowa brand'),
      'gray' => t('Gray'),
    ],
    '#default_value' => theme_get_setting('style.style_selector'),
  ];

  // Value set on submit. Read-only for admins.
  $form['style']['style_selector']['#disabled'] = TRUE;

  // Only allow access to this field for users
  // with the 'administer site configuration' permission.
  if (!\Drupal::currentUser()->hasPermission('administer site configuration')) {
    $form['style']['#access'] = FALSE;
  }

  // These fields are only available to writing university for now.
  $form['fonts'] = [
    '#type' => 'details',
    '#title' => t('Theme settings'),
    '#description' => t('Configure various theme settings for the uids_base theme.'),
    '#weight' => -1000,
    '#open' => TRUE,
    '#tree' => TRUE,
  ];

  $form['fonts']['font-family'] = [
    '#type' => 'select',
    '#title' => t('Font family'),
    '#description' => t('This option changes the font family for most text areas that are not part of a styled block component or element'),
    '#options' => [
      'sans-serif' => t('Sans serif (Roboto)'),
      'serif' => t('Serif (Zilla Slab)'),
    ],
    '#default_value' => theme_get_setting('fonts.font-family'),
  ];

  // Only allow access to these sites.
  $form['fonts']['#access'] = FALSE;
  $site_path = \Drupal::getContainer()->getParameter('site.path');

  if (
    $site_path === 'sites/writinguniversity.org' ||
    $site_path === 'sites/sandbox.uiowa.edu'
  ) {
    $form['fonts']['#access'] = TRUE;
  }

  $form['theme_settings']['#open'] = FALSE;
  $form['favicon']['#open'] = TRUE;

  // A theme setting to make it easier to control display of the footer login
  // link. This is only changeable programmatically and/or with Drush as it
  // should be on almost all the time.
  $form['footer'] = [
    '#type' => 'details',
    '#access' => FALSE,
    '#tree' => TRUE,
  ];

  $form['footer']['login_link'] = [
    '#type' => 'checkbox',
    '#title' => t('Footer login link'),
    '#description' => t('Display a login link in the footer.'),
    '#default_value' => theme_get_setting('footer.login_link') ?? TRUE,
    '#access' => FALSE,
  ];

  $form['#submit'][] = 'uids_base_form_system_theme_settings_submit';
}

/**
 * Test theme form settings submission handler.
 */
function uids_base_form_system_theme_settings_submit(&$form, FormStateInterface $form_state): void {
  // Set color option based on branding_option.
  if ($form_state->getValue(['header', 'branding_options']) == 'regents') {
    $form_state->setValue('style.style_selector', 'gray');
  }
  else {
    $form_state->setValue('style.style_selector', 'brand');
  }

  $nav_style = $form_state->getValue(['header', 'nav_style']);

  $ids = \Drupal::entityQuery('block')
    ->condition('plugin', 'superfish:main')
    ->accessCheck(TRUE)
    ->execute();

  foreach ($ids as $id) {
    // Skip 'mainnavigation' block.
    if (strpos($id, 'superfish') === FALSE) {
      continue;
    }
    $status = 0;
    $block = Block::load($id);
    if (strpos($id, $nav_style) !== FALSE) {
      $status = 1;
    }
    if ($block->status() != $status) {
      $block->setStatus($status);
      $block->save();
    }
  }
}
