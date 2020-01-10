<?php

/**
 * @file
 * Global settings for every multisite.
 */

use Drupal\Core\Installer\InstallerKernel;

/**
 * Set BLT to not override the config directories.
 *
 * This allows sites to set their own config directories instead of BLT hard-
 * coding the default site config directory.
 *
 * @see: acquia/blt/config.settings.php
 */
$blt_override_config_directories = FALSE;

// Unset the VCS config directory so cim/cex default to sync.
if (isset($config_directories['vcs'])) {
  unset($config_directories['vcs']);
}

// Prevent access to uninstalled sites through the web interface.
if (InstallerKernel::installationAttempted() && php_sapi_name() != 'cli') {
  exit;
}
