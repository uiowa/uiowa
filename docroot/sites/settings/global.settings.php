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

// Get some variables from the Acquia EnvironmentDetector or fall back to local.
// Note that $site_path is always set and exposed by the Drupal Kernel.
$ah_group = EnvironmentDetector::getAhGroup() ?: 'local';
$ah_env = EnvironmentDetector::getAhEnv() ?: 'local';

/** @var $site_path string The path to the bootstrapped site. */
$site_name = EnvironmentDetector::getSiteName($site_path);

// Set the environment indicator settings for the toolbar color and name.
switch ($ah_env) {
  case 'local':
    $settings['simple_environment_indicator'] = '#00664F local';
    break;

  case 'dev':
    $settings['simple_environment_indicator'] = '#00558C dev';
    break;

  case 'test':
    $settings['simple_environment_indicator'] = '#BD472A test';
    break;

  case 'prod':
    $settings['simple_environment_indicator'] = '#63666A prod';
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

// Override BLTs hash salt to be unique per site.
$settings['hash_salt'] = hash('sha256', $ah_group . $ah_env . $site_name);

// Compatibility with Acquia Platform Email for Symfony Mailer module.
// See https://docs.acquia.com/cloud-platform/manage/platform-email/faq/#can-i-use-symfony-mailer-with-platform-email
$settings['mailer_sendmail_commands'] = [
  ini_get('sendmail_path') . ' -t',
];

if ($ah_env !== 'local') {
  // Set this in config since the config references the actual text of the
  // command, which is different for each environment.
  $config['symfony_mailer.mailer_transport.sendmail']['configuration']['query']['command'] = ini_get('sendmail_path') . ' -t';
}

// Set recommended New Relic configuration.
// @see: https://docs.acquia.com/acquia-cloud/monitor/apm/#recommended-configuration-settings
ini_set('newrelic.loglevel', 'error');

if (extension_loaded('newrelic')) {
  newrelic_set_appname("{$site_name};{$ah_group}.{$ah_env}", '', 'true');
}

// Memory increase for large menu pages so webmasters can save changes.
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'admin/structure/menu') !== false ) {
  ini_set('memory_limit', '256M');
}

// Under Acquia Cloud, this will override the temporary folder used by
// the Webform module and might resolve issues around exporting.
// See https://www.drupal.org/project/webform/issues/2980276 for more information.
if ($ah_env !== 'local') {
  $config['webform.settings']['export']['temp_directory'] = "/mnt/gfs/{$_ENV['AH_SITE_GROUP']}.{$_ENV['AH_SITE_ENVIRONMENT']}/tmp";
}
