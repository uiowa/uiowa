<?php
/**
 * @file
 * SiteNow global settings.
 *
 * Note that this file is NOT included for every multisite but rather 
 * from individual SiteNow sites through their own include chain.
 */

// Set the sync directory to the profile.
$settings['config_sync_directory'] = DRUPAL_ROOT . '/profiles/custom/sitenow/config/sync';

/**
 * Set the environment indicator colors.
 */
$env = getenv('AH_SITE_ENVIRONMENT');

switch ($env) {
  case 'dev':
    $settings['simple_environment_indicator'] = '#4363d8 dev';
    break;

  case 'test':
    $settings['simple_environment_indicator'] = '#C3561A test';
    break;

  case 'prod':
    $settings['simple_environment_indicator'] = '#e6194b prod';
    break;

  default:
    $settings['simple_environment_indicator'] = '#31873E local';
    break;
}

/**
 * A custom theme for the offline page.
 *
 * This applies when the site is explicitly set to maintenance mode through the
 * administration page or when the database is inactive due to an error.
 * The template file should also be copied into the theme. It is located inside
 * 'core/modules/system/templates/maintenance-page.html.twig'.
 *
 * Note: This setting does not apply to installation and update pages.
 */
$settings['maintenance_theme'] = 'seven';
