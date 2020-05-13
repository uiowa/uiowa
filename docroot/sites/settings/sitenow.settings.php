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
    $settings['simple_environment_indicator'] = '#00558C dev';
    break;

  case 'test':
    $settings['simple_environment_indicator'] = '#BD472A test';
    break;

  case 'prod':
    $settings['simple_environment_indicator'] = '#63666A prod';
    break;

  default:
    $settings['simple_environment_indicator'] = '#00664F local';
    break;
}
