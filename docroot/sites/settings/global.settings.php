<?php

/**
 * @file
 * Global settings for every multisite.
 */

use Acquia\Blt\Robo\Config\ConfigInitializer;
use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\Console\Input\ArgvInput;

// Prevent access to uninstalled sites through the web interface.
if (InstallerKernel::installationAttempted() && php_sapi_name() != 'cli') {
  exit;
}

// Initialize new config object to access BLT config. Similar to ACSF.
// @see: blt.settings.php
$config_initializer = new ConfigInitializer($repo_root, new ArgvInput());
$config_initializer->setSite($site_dir);
$blt_config = $config_initializer->initialize();

/**
 * Set BLT to not override the config directories.
 *
 * This allows sites to set their own config directories instead of BLT hard-
 * coding the default site config directory.
 *
 * @see: acquia/blt/config.settings.php
 */
$blt_override_config_directories = FALSE;

if ($blt_sync_path = $blt_config->get('cm.core.dirs.sync.path')) {
  $settings['config_sync_directory'] = DRUPAL_ROOT . '/' . $blt_sync_path;
}

// Unset the VCS config directory so cim/cex default to sync.
if (isset($config_directories['vcs'])) {
  unset($config_directories['vcs']);
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
