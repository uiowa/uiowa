<?php

/**
 * @file
 * Global settings for every multisite.
 */

use Acquia\Blt\Robo\Common\EnvironmentDetector;
use Drupal\Core\Installer\InstallerKernel;

// Prevent access to uninstalled sites through the web interface.
if (InstallerKernel::installationAttempted() && php_sapi_name() != 'cli') {
  exit;
}

// Unset the VCS config directory so cim/cex default to sync.
if (isset($config_directories['vcs'])) {
  unset($config_directories['vcs']);
}

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
$settings['maintenance_theme'] = 'uids_base';

// Set recommended New Relic configuration.
// @see: https://docs.acquia.com/acquia-cloud/monitor/apm/#recommended-configuration-settings
ini_set('newrelic.loglevel', 'error');

if (extension_loaded('newrelic')) {
  $ah_group = EnvironmentDetector::getAhGroup();
  $ah_env = EnvironmentDetector::getAhEnv();
  newrelic_set_appname("{$site_dir};{$ah_group}.{$ah_env}", '', 'true');
}
