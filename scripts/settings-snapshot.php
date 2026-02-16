<?php

/**
 * @file
 * Settings snapshot script for BLT replacement validation.
 *
 * Dumps the effective settings and config values produced by the settings
 * cascade, so we can compare before/after the BLT-to-global.settings.php
 * transition.
 *
 * Usage:
 *   ddev drush -l site.uiowa.edu php:script scripts/settings-snapshot.php
 *
 * Compare:
 *   1. Run on main branch → save output to before.json
 *   2. Run on feature branch → save output to after.json
 *   3. diff before.json after.json
 */

use Drupal\Core\Site\Settings;

$settings = Settings::getAll();

// Extract the settings we care about (the ones set by the BLT cascade).
$snapshot = [
  'settings' => [
    'hash_salt' => $settings['hash_salt'] ?? NULL,
    'deployment_identifier' => $settings['deployment_identifier'] ?? NULL,
    'config_sync_directory' => $settings['config_sync_directory'] ?? NULL,
    'file_public_path' => $settings['file_public_path'] ?? NULL,
    'file_private_path' => $settings['file_private_path'] ?? NULL,
    'reverse_proxy' => $settings['reverse_proxy'] ?? NULL,
    'reverse_proxy_addresses' => $settings['reverse_proxy_addresses'] ?? [],
    'simple_environment_indicator' => $settings['simple_environment_indicator'] ?? NULL,
    'maintenance_theme' => $settings['maintenance_theme'] ?? NULL,
    'mailer_sendmail_commands' => $settings['mailer_sendmail_commands'] ?? NULL,
    'skip_permissions_hardening' => $settings['skip_permissions_hardening'] ?? NULL,
    'trusted_host_patterns' => $settings['trusted_host_patterns'] ?? NULL,
  ],
  'config_overrides' => [
    'config_split.config_split.local' => $GLOBALS['config']['config_split.config_split.local'] ?? NULL,
    'config_split.config_split.dev' => $GLOBALS['config']['config_split.config_split.dev'] ?? NULL,
    'config_split.config_split.stage' => $GLOBALS['config']['config_split.config_split.stage'] ?? NULL,
    'config_split.config_split.prod' => $GLOBALS['config']['config_split.config_split.prod'] ?? NULL,
    'config_split.config_split.ci' => $GLOBALS['config']['config_split.config_split.ci'] ?? NULL,
    'config_split.config_split.ode' => $GLOBALS['config']['config_split.config_split.ode'] ?? NULL,
    'config_split.config_split.ah_other' => $GLOBALS['config']['config_split.config_split.ah_other'] ?? NULL,
    'system.logging' => $GLOBALS['config']['system.logging'] ?? NULL,
    'symfony_mailer.mailer_transport.sendmail' => $GLOBALS['config']['symfony_mailer.mailer_transport.sendmail'] ?? NULL,
    'webform.settings' => $GLOBALS['config']['webform.settings']['export']['temp_directory'] ?? NULL,
  ],
  'meta' => [
    'site_path' => \Drupal::getContainer()->getParameter('site.path'),
    'drupal_version' => \Drupal::VERSION,
    'php_sapi' => php_sapi_name(),
    'ah_env' => getenv('AH_SITE_ENVIRONMENT') ?: 'local',
    'is_ci' => (bool) getenv('CI'),
  ],
];

echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
