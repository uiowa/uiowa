<?php
/**
 * @file
 * SiteNow global settings.
 *
 * Note that this file is NOT included for every multisite but rather
 * from individual SiteNow sites through their own include chain.
 */

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
