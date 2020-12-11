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
function uids_base_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {

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

  $top_links_limit = theme_get_setting('header.top_links_limit');
  // Get limit, otherwise limit to 2.
  $form['header']['top_links_limit'] = [
    '#type' => 'number',
    '#title' => t('Top Links Limit'),
    '#access' => FALSE,
    '#default_value' => ($top_links_limit ? $top_links_limit : 2),
  ];

  $form['theme_settings']['#open'] = FALSE;
  $form['favicon']['#open'] = TRUE;

  $form['#submit'][] = 'uids_base_form_system_theme_settings_submit';
}

/**
 * Test theme form settings submission handler.
 */
function uids_base_form_system_theme_settings_submit(&$form, FormStateInterface $form_state) {

  $nav_style = $form_state->getValue(['header', 'nav_style']);

  $ids = \Drupal::entityQuery('block')
    ->condition('plugin', 'superfish:main')
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
