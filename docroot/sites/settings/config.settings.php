<?php

/**
 * @file
 * Override BLT config sync directory to point at the SiteNow profile.
 *
 * This allows the default site to have its own split and fixes an issue with
 * PHPUnit tests failing due to config/default not being imported.
 */

$blt_override_config_directories = FALSE;
$config_directories[CONFIG_SYNC_DIRECTORY] = DRUPAL_ROOT . '/profile/custom/sitenow/config/sync';
