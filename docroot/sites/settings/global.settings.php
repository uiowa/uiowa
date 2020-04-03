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

/**
 * Set sync directory based on BLT configuration.
 *
 * This allows sites to set their own sync directory instead of BLT hard-
 * coding the default site config directory. This uses a similar method to
 * access BLT configuration as ACSF in blt.settings.php.
 *
 * @see: acquia/blt/blt.settings.php
 * @see: acquia/blt/config.settings.php
 */
$config_initializer = new ConfigInitializer($repo_root, new ArgvInput());
$config_initializer->setSite($site_dir);
$blt_config = $config_initializer->initialize();

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

// Set recommended New Relic configuration.
// @see: https://docs.acquia.com/acquia-cloud/monitor/apm/#recommended-configuration-settings
ini_set('newrelic.loglevel', 'error');
