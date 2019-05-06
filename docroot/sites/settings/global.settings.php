<?php

/**
 * @file
 * Global settings for every multisite.
 */

/**
 * Override BLT config sync directory to point at the SiteNow profile.
 *
 * This allows the default site to have its own split and fixes an issue with
 * PHPUnit tests failing due to config/default not being imported.
 */
$blt_override_config_directories = FALSE;
$config_directories[CONFIG_SYNC_DIRECTORY] = DRUPAL_ROOT . '/profiles/custom/sitenow/config/sync';

/**
 * Standardize on an escaped site directory DB include for AC.
 *
 * The default site will be set to use the uiowa database by BLT.
 */
if ($site_dir != 'default') {
  $db_name = str_replace('.', '_', $site_dir);

  if (file_exists('/var/www/site-php')) {
    require "/var/www/site-php/uiowa/{$db_name}-settings.inc";
  }
}
