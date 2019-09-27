<?php

/**
 * @file
 * Global settings for every multisite.
 */

use Sitenow\Multisite;

/**
 * Standardize on an escaped site directory DB include for AC.
 *
 * The default site will be set to use the uiowa database by BLT.
 */
if ($dir != 'default') {
  $db = Multisite::getDatabase($dir);

  if (file_exists('/var/www/site-php')) {
    require "/var/www/site-php/uiowa/{$db}-settings.inc";
  }
}

/**
 * Override BLT config sync directory to point at the SiteNow profile.
 *
 * This allows the default site to have its own split and fixes an issue with
 * PHPUnit tests failing due to config/default not being imported.
 *
 * This HAS to come after the above database snippet to override correctly.
 */
$blt_override_config_directories = FALSE;
$config_directories[CONFIG_SYNC_DIRECTORY] = DRUPAL_ROOT . '/profiles/custom/sitenow/config/sync';

// Unset the VCS config directory so cim/cex default to sync.
if (isset($config_directories['vcs'])) {
  unset($config_directories['vcs']);
}

// Require site installs through the CLI.
if (drupal_installation_attempted() && php_sapi_name() != 'cli') {
  exit;
}

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
