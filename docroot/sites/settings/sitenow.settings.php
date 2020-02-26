<?php
/**
 * @file
 * SiteNow global settings.
 *
 * Note that this file is NOT included for every multisite but rather
 * from individual SiteNow sites through their own include chain.
 */

use Acquia\Blt\Robo\Common\EnvironmentDetector;

/**
 * Environment specific SiteNow configuration.
 */
$env = EnvironmentDetector::getAhEnv() ?? 'local';

switch ($env) {
  case 'local':
    $settings['simple_environment_indicator'] = '#31873E local';
    $config['config_split.config_split.sitenow_migrate']['status'] = TRUE;

    break;

  case 'dev':
    $settings['simple_environment_indicator'] = '#4363d8 dev';
    break;

  case 'test':
    $settings['simple_environment_indicator'] = '#C3561A test';
    break;

  case 'prod':
    $settings['simple_environment_indicator'] = '#e6194b prod';
    break;
}
